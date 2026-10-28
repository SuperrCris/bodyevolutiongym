<?php

namespace App\Domain\Impresion;

use App\Models\Pago;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TicketeraServicio
{
    public function imprimir(Pago $pago): string
    {
        $pago->loadMissing(['cliente', 'membresia.plan', 'usuario']);

        try {
            $respuesta = Http::acceptJson()
                ->connectTimeout((int) config('biometria.connect_timeout'))
                ->timeout((int) config('biometria.print_timeout'))
                ->withBody($this->contenido($pago), 'text/plain; charset=UTF-8')
                ->post(rtrim((string) config('biometria.url'), '/').'/imprimir');
        } catch (ConnectionException $exception) {
            throw new TicketeraException(
                'No se pudo conectar con el puente local de impresión. Inicie biometria-java\\ejecutar.ps1.',
                previous: $exception,
            );
        }

        if ($respuesta->failed()) {
            $error = $respuesta->json('error');

            throw new TicketeraException(
                is_string($error) ? $error : 'El puente local no pudo imprimir el recibo.',
            );
        }

        $impresora = $respuesta->json('printer');

        if ($respuesta->json('printed') !== true || ! is_string($impresora) || trim($impresora) === '') {
            throw new TicketeraException('El puente local no confirmó el envío del recibo a la impresora.');
        }

        if ($respuesta->json('copies') !== 2) {
            throw new TicketeraException('El puente local no confirmó la impresión de dos recibos en un solo trabajo.');
        }

        return $impresora;
    }

    private function contenido(Pago $pago): string
    {
        $rutaLogo = public_path('images/logo.png');

        if (! is_file($rutaLogo)) {
            throw new TicketeraException('No se encontró el logo para imprimir el recibo.');
        }

        $logo = file_get_contents($rutaLogo);

        if ($logo === false) {
            throw new TicketeraException('No se pudo leer el logo para imprimir el recibo.');
        }

        $lineas = [
            'BODY EVOLUTION GYM 3',
            'JOSE ROSARIO GUTIERREZ MELENDEZ',
            'GUMR790402B66',
            '6421095410',
            str_repeat('-', 32),
            'Folio: #'.$pago->id,
            'Fecha: '.$pago->created_at?->format('d/m/Y H:i'),
            'Atendió: '.Str::squish($pago->usuario?->name ?? '—'),
            '',
            'Cliente: '.Str::squish(trim(($pago->cliente?->nombre ?? '').' '.($pago->cliente?->apellido ?? ''))),
            'Tipo: '.Str::squish($pago->membresia?->plan?->nombre ?? '—'),
            'Desde: '.$pago->membresia?->comienzo?->format('d/m/Y'),
            'Hasta: '.$pago->membresia?->finalizacion?->format('d/m/Y'),
            '',
            'Método: '.match ($pago->metodo_pago) {
                'cash' => 'Efectivo',
                'card' => 'Tarjeta',
                'transfer' => 'Transferencia',
                default => Str::squish($pago->metodo_pago),
            },
        ];

        if (filled($pago->referencia)) {
            $lineas[] = 'Referencia: '.Str::squish($pago->referencia);
        }

        $lineas[] = str_repeat('-', 32);
        $lineas[] = 'TOTAL: $'.number_format((float) $pago->monto, 2, '.', ',');
        $lineas[] = '';
        $lineas[] = '¡Gracias por tu preferencia!';

        return "BEG-PRINT-V1\n".base64_encode($logo)."\n\n".implode("\n", $lineas)."\n";
    }
}
