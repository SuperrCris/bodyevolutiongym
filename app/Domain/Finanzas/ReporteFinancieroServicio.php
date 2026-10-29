<?php

namespace App\Domain\Finanzas;

use App\Domain\Auditoria\AuditoriaServicio;
use App\Models\Egreso;
use App\Models\Pago;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReporteFinancieroServicio
{
    public const METODOS_PAGO = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencias',
        'tarjeta' => 'Tarjeta',
    ];

    private const ALIASES_METODOS_PAGO = [
        'cash' => 'efectivo',
        'card' => 'tarjeta',
        'transfer' => 'transferencia',
    ];

    /**
     * @return array{
     *     metodos: array<string, array{nombre: string, membresias: string, ajustes: list<array{concepto: string, monto: string}>, total: string}>,
     *     membresias_total: string,
     *     ajustes_total: string,
     *     total: string
     * }
     */
    public function cortePeriodo(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $membresiasPorMetodo = array_fill_keys(array_keys(self::METODOS_PAGO), 0);

        $pagos = DB::table('pagos')
            ->whereNotNull('membresia_id')
            ->whereBetween('created_at', [$desde, $hasta])
            ->select('metodo_pago')
            ->selectRaw('SUM(monto) AS total')
            ->groupBy('metodo_pago')
            ->get();

        foreach ($pagos as $pago) {
            $metodoPago = self::ALIASES_METODOS_PAGO[$pago->metodo_pago] ?? $pago->metodo_pago;

            if (array_key_exists($metodoPago, $membresiasPorMetodo)) {
                $membresiasPorMetodo[$metodoPago] += $this->centavos($pago->total);
            }
        }

        $ajustes = DB::table('reporte_ajustes_manuales')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha')
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
        $ajustesPorMetodo = [];

        if ($desde->toDateString() === $hasta->toDateString()) {
            foreach ($ajustes as $ajuste) {
                $ajustesPorMetodo[$ajuste->metodo_pago][] = [
                    'concepto' => $ajuste->concepto,
                    'monto' => (string) $ajuste->monto,
                ];
            }
        } else {
            $totalesPorMetodoYConcepto = [];

            foreach ($ajustes as $ajuste) {
                $metodoPago = $ajuste->metodo_pago;
                $concepto = $ajuste->concepto;
                $totalesPorMetodoYConcepto[$metodoPago][$concepto] =
                    ($totalesPorMetodoYConcepto[$metodoPago][$concepto] ?? 0)
                    + $this->centavos($ajuste->monto);
            }

            foreach ($totalesPorMetodoYConcepto as $metodoPago => $conceptos) {
                foreach ($conceptos as $concepto => $monto) {
                    $ajustesPorMetodo[$metodoPago][] = [
                        'concepto' => $concepto,
                        'monto' => $this->formatearCentavos($monto),
                    ];
                }
            }
        }

        $metodos = [];
        $membresiasTotal = 0;
        $ajustesTotal = 0;
        $total = 0;

        foreach (self::METODOS_PAGO as $metodoPago => $nombre) {
            $ajustesDelMetodo = $ajustesPorMetodo[$metodoPago] ?? [];
            $ajustesDelMetodo = $ajustesDelMetodo === []
                ? [
                    ['concepto' => 'Aguas', 'monto' => ''],
                    ['concepto' => 'Salidas', 'monto' => ''],
                ]
                : $ajustesDelMetodo;

            $montoMembresias = $membresiasPorMetodo[$metodoPago];
            $montoAjustes = array_reduce(
                $ajustesDelMetodo,
                fn (int $suma, array $ajuste): int => $suma + $this->centavos($ajuste['monto']),
                0,
            );
            $montoTotal = $montoMembresias + $montoAjustes;

            $metodos[$metodoPago] = [
                'nombre' => $nombre,
                'membresias' => $this->formatearCentavos($montoMembresias),
                'ajustes' => $ajustesDelMetodo,
                'total' => $this->formatearCentavos($montoTotal),
            ];
            $membresiasTotal += $montoMembresias;
            $ajustesTotal += $montoAjustes;
            $total += $montoTotal;
        }

        return [
            'metodos' => $metodos,
            'membresias_total' => $this->formatearCentavos($membresiasTotal),
            'ajustes_total' => $this->formatearCentavos($ajustesTotal),
            'total' => $this->formatearCentavos($total),
        ];
    }

    /**
     * @param  array<string, array<int, array{concepto: string, monto: string|null}>>  $ajustesPorMetodo
     */
    public function guardarAjustesManuales(
        CarbonImmutable $fecha,
        array $ajustesPorMetodo,
        int $usuarioId,
        AuditoriaServicio $auditoria,
    ): void {
        DB::transaction(function () use ($fecha, $ajustesPorMetodo, $usuarioId, $auditoria): void {
            DB::table('reporte_ajustes_manuales')
                ->where('fecha', $fecha->toDateString())
                ->delete();

            $filas = [];
            $ahora = now();

            foreach (self::METODOS_PAGO as $metodoPago => $_nombre) {
                $orden = 0;

                foreach ($ajustesPorMetodo[$metodoPago] ?? [] as $ajuste) {
                    $monto = $ajuste['monto'] ?? null;

                    if ($monto === null || $monto === '') {
                        continue;
                    }

                    $filas[] = [
                        'fecha' => $fecha->toDateString(),
                        'metodo_pago' => $metodoPago,
                        'concepto' => $ajuste['concepto'],
                        'monto' => $monto,
                        'orden' => $orden++,
                        'usuario_id' => $usuarioId,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
            }

            if ($filas !== []) {
                DB::table('reporte_ajustes_manuales')->insert($filas);
            }

            $auditoria->registrar(
                accion: 'reporte.ajustes.actualizados',
                metadatos: [
                    'fecha' => $fecha->toDateString(),
                    'cantidad' => count($filas),
                ],
                usuarioId: $usuarioId,
            );
        });
    }

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

    public function membresiasEnPeriodo(CarbonImmutable $desde, CarbonImmutable $hasta, ?int $clienteId = null): Builder
    {
        return DB::table('pagos')
            ->leftJoin('membresias', 'membresias.id', '=', 'pagos.membresia_id')
            ->leftJoin('tipo_membresias', 'tipo_membresias.id', '=', 'membresias.tipo_membresia_id')
            ->leftJoin('clientes', 'clientes.id', '=', 'pagos.id_cliente')
            ->whereNotNull('pagos.membresia_id')
            ->whereBetween('pagos.created_at', [$desde, $hasta])
            ->when($clienteId !== null, fn (Builder $query) => $query->where('pagos.id_cliente', $clienteId))
            ->addSelect(
                'clientes.id AS cliente_id',
                'clientes.nombre AS cliente_nombre',
                'clientes.apellido AS cliente_apellido',
                'pagos.created_at AS fecha',
                'pagos.monto',
                'tipo_membresias.nombre AS tipo_membresia',
            )
            ->selectRaw(
                "CASE pagos.metodo_pago WHEN 'cash' THEN 'efectivo' WHEN 'card' THEN 'tarjeta' WHEN 'transfer' THEN 'transferencia' ELSE pagos.metodo_pago END AS metodo_pago",
            )
            ->orderByDesc('pagos.created_at')
            ->orderByDesc('pagos.id');
    }

    private function centavos(string|int|float|null $monto): int
    {
        $monto = (string) ($monto ?? '0');
        $signo = str_starts_with($monto, '-') ? -1 : 1;
        $monto = ltrim($monto, '+-');
        [$entero, $decimal] = array_pad(explode('.', $monto, 2), 2, '0');
        $centavos = (int) str_pad(substr($decimal, 0, 2), 2, '0');

        return $signo * (((int) $entero * 100) + $centavos);
    }

    private function formatearCentavos(int $centavos): string
    {
        $signo = $centavos < 0 ? '-' : '';
        $centavos = abs($centavos);

        return $signo.intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
