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
        $options->mergeCells(7, 1, 8, 1);
        $options->mergeCells(10, 1, 11, 1);
        $options->mergeCells(13, 1, 14, 1);
        $options->setColumnWidth(10, 1);
        $options->setColumnWidth(30, 2);
        $options->setColumnWidth(21, 3);
        $options->setColumnWidth(18, 4);
        $options->setColumnWidth(16, 5);
        $options->setColumnWidth(28, 6);
        $options->setColumnWidth(3, 7, 10, 13);
        $options->setColumnWidth(20, 8, 11, 14);
        $options->setColumnWidth(16, 9, 12, 15);

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
            $tarjetaTituloStyle = new Style(
                fontBold: true,
                fontSize: 13,
                fontColor: 'FFFFFF',
                backgroundColor: '0F172A',
                cellAlignment: CellAlignment::CENTER,
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

            $valoresTitulo = array_fill(0, 15, null);
            $valoresTitulo[0] = 'REPORTE DE MEMBRESÍAS · BODY EVOLUTION GYM';
            $estilosTitulo = [0 => $tituloStyle];

            foreach (array_values($corte['metodos']) as $indice => $metodo) {
                $columna = [7, 10, 13][$indice];
                $valoresTitulo[$columna] = strtoupper($metodo['nombre']);
                $estilosTitulo[$columna] = $tarjetaTituloStyle;
            }

            $writer->addRow(Row::fromValuesWithStyles($valoresTitulo, $estilosTitulo, 34));

            $valoresMeta = array_fill(0, 15, null);
            $valoresMeta[0] = 'Periodo';
            $valoresMeta[1] = $desde->format('d/m/Y H:i').' - '.$hasta->format('d/m/Y H:i');
            $estilosMeta = [0 => $metaStyle, 1 => $metaStyle];

            foreach ([7, 10, 13] as $columna) {
                $valoresMeta[$columna] = 'Concepto';
                $valoresMeta[$columna + 1] = 'Importe';
                $estilosMeta[$columna] = $encabezadoStyle;
                $estilosMeta[$columna + 1] = $encabezadoStyle;
            }

            $writer->addRow(Row::fromValuesWithStyles($valoresMeta, $estilosMeta));

            $encabezados = array_fill(0, 15, null);
            foreach ([
                'ID',
                'Nombre',
                'Fecha y hora',
                'Tipo de pago',
                'Monto',
                'Tipo de membresía',
            ] as $indice => $encabezado) {
                $encabezados[$indice] = $encabezado;
            }

            $estilosEncabezado = array_fill(0, 6, $encabezadoStyle);
            foreach ([
                7 => 'Membresías',
                10 => 'Membresías',
                13 => 'Membresías',
            ] as $columna => $concepto) {
                $indiceMetodo = (int) (($columna - 7) / 3);
                $encabezados[$columna] = $concepto;
                $encabezados[$columna + 1] = (float) array_values($corte['metodos'])[$indiceMetodo]['membresias'];
                $estilosEncabezado[$columna] = $cuerpoStyle;
                $estilosEncabezado[$columna + 1] = $importeStyle;
            }

            $writer->addRow(Row::fromValuesWithStyles($encabezados, $estilosEncabezado));

            $filasResumen = [];
            $metodos = array_values($corte['metodos']);

            foreach ($metodos as $indice => $metodo) {
                $filasMetodo = [];

                foreach ($metodo['ajustes'] as $ajuste) {
                    $filasMetodo[] = [
                        $ajuste['concepto'],
                        $ajuste['monto'] === '' ? 0.0 : (float) $ajuste['monto'],
                    ];
                }

                $filasMetodo[] = ['TOTAL', (float) $metodo['total']];
                $filasResumen[$indice] = $filasMetodo;
            }

            $iteradorMembresias = (static function () use ($membresias): iterable {
                yield from $membresias;
            })();
            $iteradorMembresias->rewind();
            $hayMembresias = $iteradorMembresias->valid();
            $cantidadFilasMembresias = 0;
            $filasDeResumen = max(array_map('count', $filasResumen));

            for (
                $indiceFila = 0;
                $hayMembresias || $indiceFila < $filasDeResumen;
                $indiceFila++
            ) {
                $fila = array_fill(0, 15, null);
                $estilosFila = [];

                if ($hayMembresias) {
                    $membresia = $iteradorMembresias->current();
                    $fila[0] = (int) $membresia->cliente_id;
                    $fila[1] = trim($membresia->cliente_nombre.' '.$membresia->cliente_apellido);
                    $fila[2] = CarbonImmutable::parse($membresia->fecha)->format('d/m/Y H:i');
                    $fila[3] = $this->nombreMetodo($membresia->metodo_pago);
                    $fila[4] = (float) $membresia->monto;
                    $fila[5] = $membresia->tipo_membresia ?? 'Membresía';

                    $estiloFila = $cantidadFilasMembresias % 2 === 0 ? $cuerpoStyle : $cuerpoAlternoStyle;
                    $estilosFila = array_fill(0, 6, $estiloFila);
                    $estilosFila[4] = $importeStyle;
                    $cantidadFilasMembresias++;

                    $iteradorMembresias->next();
                    $hayMembresias = $iteradorMembresias->valid();
                }

                foreach ($filasResumen as $indiceMetodo => $filasMetodo) {
                    if (! isset($filasMetodo[$indiceFila])) {
                        continue;
                    }

                    [$concepto, $monto] = $filasMetodo[$indiceFila];
                    $columna = [7, 10, 13][$indiceMetodo];
                    $esTotal = $concepto === 'TOTAL';
                    $fila[$columna] = $concepto;
                    $fila[$columna + 1] = $monto;
                    $estilosFila[$columna] = $esTotal ? $totalStyle : $cuerpoStyle;
                    $estilosFila[$columna + 1] = $esTotal
                        ? $totalImporteStyle
                        : ($monto < 0 ? $importeNegativoStyle : $importeStyle);
                }

                $writer->addRow(Row::fromValuesWithStyles($fila, $estilosFila));
            }

            $filaTotal = array_fill(0, 15, null);
            $filaTotal[0] = 'TOTAL MEMBRESÍAS';
            $filaTotal[4] = (float) $corte['membresias_total'];
            $filaTotal[7] = 'TOTAL DEL CORTE';
            $filaTotal[8] = (float) $corte['total'];
            $writer->addRow(Row::fromValuesWithStyles(
                $filaTotal,
                [
                    0 => $totalStyle,
                    1 => $totalStyle,
                    2 => $totalStyle,
                    3 => $totalStyle,
                    4 => $totalImporteStyle,
                    5 => $totalStyle,
                    7 => $totalStyle,
                    8 => $totalImporteStyle,
                ],
            ));

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
}
