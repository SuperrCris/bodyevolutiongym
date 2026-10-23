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
