<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\ReporteFinancieroServicio;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportesController extends Controller
{
    public function index(Request $request, ReporteFinancieroServicio $reporte): View
    {
        [$desde, $hasta] = $this->rango($request);

        return view('reportes.index', [
            'desde' => $desde,
            'hasta' => $hasta,
            'resumen' => $reporte->resumen($desde, $hasta),
            'movimientos' => $reporte->movimientos($desde, $hasta)
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function exportar(Request $request, ReporteFinancieroServicio $reporte): StreamedResponse
    {
        [$desde, $hasta] = $this->rango($request);
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
                    $movimiento->fecha,
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

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function rango(Request $request): array
    {
        $datos = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $desde = CarbonImmutable::parse($datos['desde'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $hasta = CarbonImmutable::parse($datos['hasta'] ?? now()->endOfDay()->toDateString())->endOfDay();

        if ($hasta->lessThan($desde)) {
            throw ValidationException::withMessages([
                'hasta' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            ]);
        }

        return [$desde, $hasta];
    }
}
