<?php

namespace App\Domain\Finanzas;

use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class ReporteDiarioExcelExportador
{
    /**
     * @param array{
     *     metodos: array<string, array{nombre: string, membresias: string, ajustes: list<array{concepto: string, monto: string}>, total: string}>,
     *     membresias_total: string,
     *     ajustes_total: string,
     *     total: string
     * } $corte
     * @param  iterable<object>  $membresias
     */
    public function crear(
        CarbonImmutable $desde,
        CarbonImmutable $hasta,
        array $corte,
        iterable $membresias,
    ): string {
        $ruta = tempnam(sys_get_temp_dir(), 'reporte-membresias-');

        if ($ruta === false) {
            throw new RuntimeException('No fue posible preparar el archivo Excel.');
        }

        $options = new Options(
            SHOULD_CREATE_NEW_SHEETS_AUTOMATICALLY: false,
            DEFAULT_COLUMN_WIDTH: 15,
            DEFAULT_ROW_HEIGHT: 22,
        );
        $options->mergeCells(0, 1, 5, 1);
        $options->mergeCells(1, 2, 5, 2);
        $options->setColumnWidth(10, 1);
        $options->setColumnWidth(30, 2);
        $options->setColumnWidth(21, 3);
        $options->setColumnWidth(18, 4);
        $options->setColumnWidth(16, 5);
        $options->setColumnWidth(28, 6);

        $writer = new Writer($options);
        $creado = false;

        try {
            $writer->openToFile($ruta);
            $writer->getCurrentSheet()->setName('Reporte');

            $tituloStyle = new Style(
                fontBold: true,
                fontSize: 18,
                fontColor: 'FFFFFF',
                backgroundColor: '0F766E',
                cellVerticalAlignment: CellVerticalAlignment::CENTER,
            );
            $metaStyle = new Style(
                fontBold: true,
                fontColor: '475569',
                backgroundColor: 'F1F5F9',
            );
            $encabezadoStyle = new Style(
                fontBold: true,
                fontColor: 'FFFFFF',
                backgroundColor: '334155',
                cellAlignment: CellAlignment::CENTER,
                cellVerticalAlignment: CellVerticalAlignment::CENTER,
            );
            $cuerpoStyle = new Style(
                fontColor: '0F172A',
                backgroundColor: 'FFFFFF',
                cellVerticalAlignment: CellVerticalAlignment::CENTER,
            );
            $cuerpoAlternoStyle = new Style(
                fontColor: '0F172A',
                backgroundColor: 'F8FAFC',
                cellVerticalAlignment: CellVerticalAlignment::CENTER,
            );
            $importeStyle = new Style(
                fontColor: '0F766E',
                format: '$#,##0.00;[Red]-$#,##0.00',
                cellAlignment: CellAlignment::RIGHT,
                cellVerticalAlignment: CellVerticalAlignment::CENTER,
            );
            $importeNegativoStyle = new Style(
                fontColor: 'BE123C',
                format: '$#,##0.00;[Red]-$#,##0.00',
                cellAlignment: CellAlignment::RIGHT,
                cellVerticalAlignment: CellVerticalAlignment::CENTER,
            );
            $totalStyle = new Style(
                fontBold: true,
                fontColor: 'FFFFFF',
                backgroundColor: '0F766E',
                cellVerticalAlignment: CellVerticalAlignment::CENTER,
            );
            $totalImporteStyle = new Style(
                fontBold: true,
                fontColor: 'FFFFFF',
                backgroundColor: '0F766E',
                format: '$#,##0.00;[Red]-$#,##0.00',
                cellAlignment: CellAlignment::RIGHT,
                cellVerticalAlignment: CellVerticalAlignment::CENTER,
            );

            $valoresTitulo = array_fill(0, 6, null);
            $valoresTitulo[0] = 'REPORTE DE MEMBRESÍAS · BODY EVOLUTION GYM';
            $writer->addRow(Row::fromValuesWithStyles($valoresTitulo, [0 => $tituloStyle], 34));

            $valoresMeta = array_fill(0, 6, null);
            $valoresMeta[0] = 'Periodo';
            $valoresMeta[1] = $desde->format('d/m/Y H:i').' - '.$hasta->format('d/m/Y H:i');
            $estilosMeta = [0 => $metaStyle, 1 => $metaStyle];
            $writer->addRow(Row::fromValuesWithStyles($valoresMeta, $estilosMeta));

            $encabezados = [
                'ID',
                'Nombre',
                'Fecha y hora',
                'Tipo de pago',
                'Monto',
                'Tipo de membresía',
            ];
            $writer->addRow(Row::fromValuesWithStyles(
                $encabezados,
                array_fill(0, 6, $encabezadoStyle),
            ));

            $cantidadFilasMembresias = 0;

            foreach ($membresias as $membresia) {
                $fila = [
                    $membresia->cliente_id === null ? '—' : (int) $membresia->cliente_id,
                    $membresia->cliente_id === null
                        ? (trim((string) $membresia->nombre_comprador) ?: 'Venta sin cliente')
                        : trim($membresia->cliente_nombre.' '.$membresia->cliente_apellido),
                    CarbonImmutable::parse($membresia->fecha, 'UTC')
                        ->setTimezone((string) config('app.display_timezone'))
                        ->format('d/m/Y H:i'),
                    $this->nombreMetodo($membresia->metodo_pago),
                    (float) $membresia->monto,
                    $membresia->tipo_membresia ?? 'Membresía',
                ];
                $estiloFila = $cantidadFilasMembresias % 2 === 0 ? $cuerpoStyle : $cuerpoAlternoStyle;
                $estilosFila = array_fill(0, 6, $estiloFila);
                $estilosFila[4] = $importeStyle;
                $cantidadFilasMembresias++;

                $writer->addRow(Row::fromValuesWithStyles($fila, $estilosFila));
            }

            $membresiasCentavos = $this->centavos($corte['membresias_total']);
            $ajustesEfectivo = $corte['metodos']['efectivo']['ajustes'];
            $efectivoCentavos = 0;
            $transferenciasCentavos = $this->centavos($corte['metodos']['transferencia']['membresias']);

            $this->agregarFilaResumen($writer, 'SUBTOTAL', $membresiasCentavos, true, $totalStyle, $totalImporteStyle);

            foreach ($ajustesEfectivo as $ajuste) {
                $montoCentavos = $this->centavos($ajuste['monto']);
                $efectivoCentavos += $montoCentavos;
                $this->agregarFilaResumen(
                    $writer,
                    strtoupper($ajuste['concepto']),
                    $montoCentavos,
                    false,
                    $cuerpoStyle,
                    $montoCentavos < 0 ? $importeNegativoStyle : $importeStyle,
                );
            }

            foreach ($corte['metodos']['transferencia']['ajustes'] as $ajuste) {
                $transferenciasCentavos += $this->centavos($ajuste['monto']);
            }

            $this->agregarFilaResumen(
                $writer,
                'TRANSFERENCIAS',
                -$transferenciasCentavos,
                false,
                $cuerpoStyle,
                $transferenciasCentavos > 0 ? $importeNegativoStyle : $importeStyle,
            );

            $this->agregarFilaResumen(
                $writer,
                'TOTAL',
                $membresiasCentavos + $efectivoCentavos - $transferenciasCentavos,
                true,
                $totalStyle,
                $totalImporteStyle,
            );

            $creado = true;

            return $ruta;
        } finally {
            if (! $creado && is_file($ruta)) {
                unlink($ruta);
            }

            $writer->close();
        }
    }

    private function nombreMetodo(string $metodoPago): string
    {
        return match ($metodoPago) {
            'efectivo' => 'Efectivo',
            'tarjeta' => 'Tarjeta',
            'transferencia' => 'Transferencia',
            default => $metodoPago,
        };
    }

    private function agregarFilaResumen(
        Writer $writer,
        string $concepto,
        int $centavos,
        bool $destacado,
        Style $estiloConcepto,
        Style $estiloImporte,
    ): void {
        $fila = [
            null,
            null,
            null,
            $concepto,
            $centavos / 100,
            null,
        ];
        $estilos = [
            3 => $estiloConcepto,
            4 => $estiloImporte,
        ];

        if ($destacado) {
            $estilos += array_fill(0, 6, $estiloConcepto);
            $estilos[4] = $estiloImporte;
        }

        $writer->addRow(Row::fromValuesWithStyles($fila, $estilos));
    }

    private function centavos(string $monto): int
    {
        $monto = (string) ($monto === '' ? '0' : $monto);
        $signo = str_starts_with($monto, '-') ? -1 : 1;
        $monto = ltrim($monto, '+-');
        [$entero, $decimal] = array_pad(explode('.', $monto, 2), 2, '0');

        return $signo * (((int) $entero * 100) + (int) str_pad(substr($decimal, 0, 2), 2, '0'));
    }
}
