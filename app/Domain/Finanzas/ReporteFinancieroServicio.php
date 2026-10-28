<?php

namespace App\Domain\Finanzas;

use App\Models\Egreso;
use App\Models\Pago;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReporteFinancieroServicio
{
    /**
     * @return array{ingresos: string, egresos: string, balance: string, ingresos_por_tipo: Collection<int, object>, egresos_por_categoria: Collection<int, object>}
     */
    public function resumen(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $ingresosMembresias = $this->centavos(Pago::query()
            ->whereNotNull('membresia_id')
            ->whereBetween('created_at', [$desde, $hasta])
            ->sum('monto'));
        $ingresosAdicionales = $this->centavos(Pago::query()
            ->whereNull('membresia_id')
            ->whereBetween('created_at', [$desde, $hasta])
            ->sum('monto'));
        $totalIngresos = $ingresosMembresias + $ingresosAdicionales;
        $totalEgresos = $this->centavos(Egreso::query()
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->sum('monto'));

        return [
            'ingresos' => $this->formatearCentavos($totalIngresos),
            'egresos' => $this->formatearCentavos($totalEgresos),
            'balance' => $this->formatearCentavos($totalIngresos - $totalEgresos),
            'ingresos_por_tipo' => collect([
                (object) [
                    'categoria' => 'Membresías',
                    'total' => $this->formatearCentavos($ingresosMembresias),
                ],
                (object) [
                    'categoria' => 'Otros ingresos',
                    'total' => $this->formatearCentavos($ingresosAdicionales),
                ],
            ]),
            'egresos_por_categoria' => Egreso::query()
                ->whereDate('fecha', '>=', $desde->toDateString())
                ->whereDate('fecha', '<=', $hasta->toDateString())
                ->select('categoria')
                ->selectRaw('SUM(monto) AS total')
                ->groupBy('categoria')
                ->orderBy('categoria')
                ->get(),
        ];
    }

    public function movimientos(CarbonImmutable $desde, CarbonImmutable $hasta): Builder
    {
        $ingresos = DB::table('pagos')
            ->leftJoin('membresias', 'membresias.id', '=', 'pagos.membresia_id')
            ->leftJoin('tipo_membresias', 'tipo_membresias.id', '=', 'membresias.tipo_membresia_id')
            ->whereBetween('pagos.created_at', [$desde, $hasta])
            ->selectRaw('? AS tipo_movimiento', ['ingreso'])
            ->selectRaw(
                'CASE WHEN pagos.membresia_id IS NULL THEN ? ELSE ? END AS categoria',
                ['Otros ingresos', 'Membresías'],
            )
            ->selectRaw('COALESCE(tipo_membresias.nombre, ?) AS concepto', ['Ingreso adicional'])
            ->addSelect('pagos.created_at AS fecha', 'pagos.metodo_pago')
            ->selectRaw('pagos.monto AS ingreso, 0 AS egreso, pagos.id AS id');

        $egresos = DB::table('egresos')
            ->whereDate('egresos.fecha', '>=', $desde->toDateString())
            ->whereDate('egresos.fecha', '<=', $hasta->toDateString())
            ->selectRaw('? AS tipo_movimiento', ['egreso'])
            ->addSelect('egresos.categoria', 'egresos.concepto', 'egresos.fecha', 'egresos.metodo_pago')
            ->selectRaw('0 AS ingreso, egresos.monto AS egreso, egresos.id AS id');

        return DB::query()
            ->fromSub($ingresos->unionAll($egresos), 'movimientos')
            ->select([
                'tipo_movimiento',
                'categoria',
                'concepto',
                'fecha',
                'metodo_pago',
                'ingreso',
                'egreso',
                'id',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }

    private function centavos(string|int|float|null $monto): int
    {
        $monto = (string) ($monto ?? '0');
        [$entero, $decimal] = array_pad(explode('.', $monto, 2), 2, '0');
        $centavos = (int) str_pad(substr($decimal, 0, 2), 2, '0');

        return ((int) $entero * 100) + $centavos;
    }

    private function formatearCentavos(int $centavos): string
    {
        $signo = $centavos < 0 ? '-' : '';
        $centavos = abs($centavos);

        return $signo.intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
