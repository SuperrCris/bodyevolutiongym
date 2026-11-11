<?php

namespace App\Http\Controllers;

use App\Domain\Auditoria\AuditoriaServicio;
use App\Domain\Finanzas\ReporteDiarioExcelExportador;
use App\Domain\Finanzas\ReporteFinancieroServicio;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportesController extends Controller
{
    public function index(Request $request, ReporteFinancieroServicio $reporte): View
    {
        [$desde, $hasta] = $this->rango($request);
        $clienteId = $this->clienteId($request);

        return view('reportes.index', [
            'desde' => $desde,
            'hasta' => $hasta,
            'clienteId' => $clienteId,
            'puedeEditarAjustes' => $desde->toDateString() === $hasta->toDateString(),
            'corte' => $reporte->cortePeriodo($desde, $hasta),
            'movimientos' => $reporte->membresiasEnPeriodo($desde, $hasta, $clienteId)
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function guardarAjustes(
        Request $request,
        ReporteFinancieroServicio $reporte,
        AuditoriaServicio $auditoria,
    ): RedirectResponse {
        $reglas = [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'ajustes' => ['required', 'array:efectivo,transferencia,tarjeta'],
        ];

        foreach (ReporteFinancieroServicio::METODOS_PAGO as $metodoPago => $nombre) {
            $reglas['ajustes.'.$metodoPago] = ['present', 'array'];
            $reglas['ajustes.'.$metodoPago.'.*'] = ['array:concepto,monto'];
            $reglas['ajustes.'.$metodoPago.'.*.concepto'] = ['required', 'string', 'max:80'];
            $reglas['ajustes.'.$metodoPago.'.*.monto'] = [
                'nullable',
                'numeric',
                'decimal:0,2',
                'between:-99999999.99,99999999.99',
            ];
        }

        $datos = $request->validate($reglas);
        [$desde, $hasta] = $this->rango($request);

        if ($desde->toDateString() !== $hasta->toDateString() || $desde->toDateString() !== $datos['fecha']) {
            throw ValidationException::withMessages([
                'fecha' => 'Los ajustes manuales solo pueden editarse para un solo día.',
            ]);
        }

        $reporte->guardarAjustesManuales(
            $desde->startOfDay(),
            $datos['ajustes'],
            $request->user()->id,
            $auditoria,
        );

        $parametros = $request->hasAny(['desde', 'hasta', 'hora_desde', 'hora_hasta'])
            ? $this->parametrosRango($desde, $hasta)
            : ['fecha' => $desde->toDateString()];

        return to_route('reportes.index', $parametros)
            ->with('success', 'Ajustes del corte guardados correctamente.');
    }

    public function exportarExcel(
        Request $request,
        ReporteFinancieroServicio $reporte,
        ReporteDiarioExcelExportador $excel,
    ): BinaryFileResponse {
        [$desde, $hasta] = $this->rango($request);
        $clienteId = $this->clienteId($request);
        $ruta = $excel->crear(
            $desde,
            $hasta,
            $reporte->cortePeriodo($desde, $hasta),
            $reporte->membresiasEnPeriodo($desde, $hasta, $clienteId)->cursor(),
        );

        return response()
            ->download(
                $ruta,
                'reporte-membresias-'.$desde->format('Ymd_Hi').'-'.$hasta->format('Ymd_Hi').'.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }

    public function exportar(Request $request, ReporteFinancieroServicio $reporte): StreamedResponse
    {
        $ahora = CarbonImmutable::now((string) config('app.display_timezone'));
        [$desde, $hasta] = $this->rango($request, $ahora->startOfMonth(), $ahora->endOfDay());
        $movimientos = $reporte->movimientos($desde, $hasta);
        $nombreArchivo = 'reporte-financiero-'.$desde->toDateString().'-'.$hasta->toDateString().'.csv';

        return response()->streamDownload(function () use ($movimientos): void {
            $salida = fopen('php://output', 'w');

            if ($salida === false) {
                throw new RuntimeException('No fue posible generar el archivo CSV.');
            }

            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, [
                'Tipo',
                'Categoría',
                'Concepto',
                'Fecha',
                'Método de pago',
                'Ingreso',
                'Egreso',
            ], ',', '"', '\\');

            foreach ($movimientos->cursor() as $movimiento) {
                fputcsv($salida, [
                    $movimiento->tipo_movimiento,
                    $this->protegerCeldaCsv($movimiento->categoria),
                    $this->protegerCeldaCsv($movimiento->concepto),
                    $movimiento->tipo_movimiento === 'ingreso'
                        ? CarbonImmutable::parse($movimiento->fecha, 'UTC')
                            ->setTimezone((string) config('app.display_timezone'))
                            ->format('Y-m-d H:i:s')
                        : $movimiento->fecha,
                    $this->protegerCeldaCsv($movimiento->metodo_pago),
                    $movimiento->ingreso,
                    $movimiento->egreso,
                ], ',', '"', '\\');
            }

            fclose($salida);
        }, $nombreArchivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function protegerCeldaCsv(?string $valor): string
    {
        $valor ??= '';

        return preg_match('/^[=+\-@\t\r]/', $valor) === 1 ? "'".$valor : $valor;
    }

    private function clienteId(Request $request): ?int
    {
        $datos = $request->validate([
            'cliente_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return isset($datos['cliente_id']) ? (int) $datos['cliente_id'] : null;
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function rango(
        Request $request,
        ?CarbonImmutable $desdePredeterminado = null,
        ?CarbonImmutable $hastaPredeterminado = null,
    ): array {
        $datos = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'hora_desde' => ['nullable', 'date_format:H:i'],
            'hora_hasta' => ['nullable', 'date_format:H:i'],
        ]);

        $zonaHoraria = (string) config('app.display_timezone');
        $fechaPredeterminada = CarbonImmutable::now($zonaHoraria)->toDateString();
        $desdeFecha = $datos['desde']
            ?? $datos['fecha']
            ?? $desdePredeterminado?->toDateString()
            ?? $datos['hasta']
            ?? $fechaPredeterminada;
        $hastaFecha = $datos['hasta']
            ?? $datos['fecha']
            ?? $hastaPredeterminado?->toDateString()
            ?? $datos['desde']
            ?? $fechaPredeterminada;
        $horaDesde = $datos['hora_desde']
            ?? (($datos['fecha'] ?? null) === null ? $desdePredeterminado?->format('H:i') : null)
            ?? '00:00';
        $horaHasta = $datos['hora_hasta']
            ?? (($datos['fecha'] ?? null) === null ? $hastaPredeterminado?->format('H:i') : null)
            ?? '23:59';
        $desde = CarbonImmutable::parse($desdeFecha.' '.$horaDesde, $zonaHoraria);
        $hasta = CarbonImmutable::parse($hastaFecha.' '.$horaHasta, $zonaHoraria)->endOfMinute();

        if ($hasta->lessThan($desde)) {
            throw ValidationException::withMessages([
                'hasta' => 'El momento final debe ser igual o posterior al momento inicial.',
            ]);
        }

        return [$desde, $hasta];
    }

    /**
     * @return array{desde: string, hasta: string, hora_desde: string, hora_hasta: string}
     */
    private function parametrosRango(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'hora_desde' => $desde->format('H:i'),
            'hora_hasta' => $hasta->format('H:i'),
        ];
    }
}
