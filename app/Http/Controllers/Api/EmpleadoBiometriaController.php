<?php

namespace App\Http\Controllers\Api;

use App\Domain\Auditoria\AuditoriaServicio;
use App\Domain\Biometria\VerificadorBiometrico;
use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmpleadoBiometriaController extends Controller
{
    public function iniciarEnrolar(
        Request $request,
        Usuario $empleado,
        VerificadorBiometrico $verifier,
    ): JsonResponse {
        $data = $request->validate([
            'terminal_identifier' => ['nullable', 'string', 'max:100'],
        ]);

        $verifier->iniciarEnrolarEmpleado(
            $empleado,
            $data['terminal_identifier'] ?? (string) config('biometria.terminal'),
        );

        return response()->json(['en_curso' => true], 202);
    }

    public function progresoEnrolar(
        Usuario $empleado,
        VerificadorBiometrico $verifier,
    ): JsonResponse {
        return response()->json($verifier->progresoEnrolarEmpleado($empleado));
    }

    public function finalizarEnrolar(
        Usuario $empleado,
        VerificadorBiometrico $verifier,
        AuditoriaServicio $auditoria,
    ): JsonResponse {
        $credencial = $verifier->finalizarEnrolarEmpleado($empleado);
        $auditoria->registrar('empleado.huella.enrolada', $empleado, [
            'id_credencial' => $credencial['id_credencial'],
            'proveedor' => $credencial['proveedor'],
        ]);

        return response()->json($credencial, 201);
    }
}
