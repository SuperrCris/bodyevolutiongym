<?php

namespace App\Http\Controllers;

use App\Domain\Ajustes\AjustesTicketServicio;
use App\Domain\Ajustes\RespaldoBaseDatosServicio;
use App\Domain\Auditoria\AuditoriaServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AjustesController extends Controller
{
    public function index(Request $request, AjustesTicketServicio $ajustesTicket): View
    {
        return view('ajustes.index', [
            'esAdministrador' => $request->user()->esAdministrador(),
            'configuracionTicket' => $ajustesTicket->obtenerConfiguracion(),
            'duracionNotificacion' => $request->user()->duracion_notificacion_segundos,
        ]);
    }

    public function descargarRespaldo(RespaldoBaseDatosServicio $respaldo): BinaryFileResponse|RedirectResponse
    {
        try {
            $ruta = $respaldo->generar();
        } catch (RuntimeException $exception) {
            report($exception);

            return to_route('ajustes.index')->with(
                'error',
                'No se pudo generar el respaldo de la base de datos. Revisa la configuración del servidor.',
            );
        }

        $nombreArchivo = 'respaldo-base-datos-'.now()->format('Y-m-d_His').'.sql';

        return response()->download($ruta, $nombreArchivo, [
            'Cache-Control' => 'no-store, private',
            'Content-Type' => 'application/sql',
        ])->deleteFileAfterSend(true);
    }

    public function actualizarNotificaciones(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'duracion_notificacion_segundos' => ['required', 'integer', 'between:5,120'],
        ], [
            'duracion_notificacion_segundos.between' => 'El tiempo debe estar entre 5 y 120 segundos.',
            'duracion_notificacion_segundos.integer' => 'El tiempo debe expresarse en segundos enteros.',
            'duracion_notificacion_segundos.required' => 'Selecciona el tiempo de duración de la notificación.',
        ]);

        $request->user()->update([
            'duracion_notificacion_segundos' => (int) $datos['duracion_notificacion_segundos'],
        ]);

        return to_route('ajustes.index')->with('success', 'Se actualizó el tiempo de las notificaciones.');
    }

    public function actualizarTicket(
        Request $request,
        AjustesTicketServicio $ajustesTicket,
        AuditoriaServicio $auditoria,
    ): RedirectResponse {
        foreach (['encabezado', 'pie'] as $campo) {
            $valor = $request->input($campo);

            if (is_string($valor)) {
                $request->merge([
                    $campo => trim(str_replace(["\r\n", "\r"], "\n", $valor)),
                ]);
            }
        }

        $datos = $request->validate([
            'encabezado' => [
                'present',
                'nullable',
                'string',
                'max:500',
                'not_regex:/[\x00-\x09\x0B-\x1F\x7F]/',
            ],
            'pie' => [
                'present',
                'nullable',
                'string',
                'max:500',
                'not_regex:/[\x00-\x09\x0B-\x1F\x7F]/',
            ],
        ], [
            'encabezado.max' => 'El encabezado no puede superar los 500 caracteres.',
            'encabezado.not_regex' => 'El encabezado contiene caracteres no permitidos.',
            'pie.max' => 'El pie de página no puede superar los 500 caracteres.',
            'pie.not_regex' => 'El pie de página contiene caracteres no permitidos.',
        ]);

        DB::transaction(function () use ($ajustesTicket, $auditoria, $datos): void {
            $ajustesTicket->guardarConfiguracion(
                $datos['encabezado'] ?? '',
                $datos['pie'] ?? '',
            );

            $auditoria->registrar('ajustes.ticket.actualizados', metadatos: [
                'campos' => ['encabezado', 'pie'],
            ]);
        });

        return to_route('ajustes.index')->with('success', 'Se actualizó el encabezado y pie del ticket.');
    }
}
