<?php

namespace App\Http\Controllers\Api;

use App\Domain\Biometria\VerificadorBiometrico;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiometricController extends Controller
{
    public function verify(Request $request, VerificadorBiometrico $verifier): JsonResponse
    {
        $data = $request->validate([
            'capture_token' => ['required', 'string', 'max:1048576'],
            'terminal_identifier' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($verifier->verificar(
            $data['capture_token'],
            $data['terminal_identifier'] ?? (string) config('biometria.terminal'),
        ));
    }

    /**
     * Sondeo rápido de la lectura continua: identifica al cliente si hay una huella nueva.
     */
    public function scan(Request $request, VerificadorBiometrico $verifier): JsonResponse
    {
        $data = $request->validate(['after' => ['nullable', 'integer', 'min:0']]);
        $lectura = $verifier->escuchar(isset($data['after']) ? (int) $data['after'] : null);

        if ($lectura['sample'] === null) {
            return response()->json(['seq' => $lectura['seq'], 'detected' => false]);
        }

        $resultado = $verifier->verificar($lectura['sample'], (string) config('biometria.terminal'));
        $respuesta = ['seq' => $lectura['seq'], 'detected' => true, 'matched' => $resultado['matched']];

        if ($resultado['matched']) {
            $cliente = Cliente::query()->findOrFail($resultado['id_cliente']);
            $membresia = $cliente->membresias()->with('plan')->latest('finalizacion')->first();
            $respuesta['cliente'] = [
                'id' => $cliente->id,
                'nombre' => trim($cliente->nombre.' '.$cliente->apellido),
                'plan' => $membresia?->plan?->nombre,
                'vigente' => $membresia?->estaActiva() ?? false,
                'vence' => $membresia?->finalizacion?->format('d/m/Y'),
            ];
        }

        return response()->json($respuesta);
    }

    public function capture(Request $request, VerificadorBiometrico $verifier): JsonResponse
    {
        $data = $request->validate([
            'terminal_identifier' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'capture_token' => $verifier->capturar(
                $data['terminal_identifier'] ?? (string) config('biometria.terminal'),
            ),
        ]);
    }

    public function enroll(Request $request, Cliente $cliente, VerificadorBiometrico $verifier): JsonResponse
    {
        $data = $request->validate([
            'terminal_identifier' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(
            $verifier->enrolar(
                $cliente,
                $data['terminal_identifier'] ?? (string) config('biometria.terminal'),
            ),
            201,
        );
    }
}
