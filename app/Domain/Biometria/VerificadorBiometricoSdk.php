<?php

namespace App\Domain\Biometria;

use App\Models\Cliente;
use App\Models\CredencialBiometrica;
use App\Models\CredencialBiometricaEmpleado;
use App\Models\Usuario;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VerificadorBiometricoSdk implements VerificadorBiometrico
{
    public function verificar(string $captureToken, string $terminalIdentifier): array
    {
        $credenciales = CredencialBiometrica::query()
            ->where('estado', true)
            ->get();

        foreach ($credenciales as $credencial) {
            $resultado = $this->solicitar('/verificar', [
                'sample' => $captureToken,
                'template' => $credencial->plantilla,
                'terminal' => $terminalIdentifier,
            ]);

            if (($resultado['matched'] ?? false) === true) {
                return [
                    'matched' => true,
                    'id_cliente' => $credencial->id_cliente,
                    'id_externo' => $credencial->id_externo,
                ];
            }
        }

        return ['matched' => false];
    }

    public function verificarEmpleado(string $captureToken, string $terminalIdentifier): array
    {
        $credenciales = CredencialBiometricaEmpleado::query()
            ->where('estado', true)
            ->with('usuario:id')
            ->get();
        $idUsuarioCoincidente = null;

        foreach ($credenciales as $credencial) {
            $idUsuario = (int) $credencial->usuario_id;
            $resultado = $this->solicitar('/verificar', [
                'sample' => $captureToken,
                'template' => $credencial->plantilla,
                'terminal' => $terminalIdentifier,
            ], 5);

            if (($resultado['matched'] ?? false) !== true) {
                continue;
            }

            if ($idUsuarioCoincidente !== null && $idUsuarioCoincidente !== $idUsuario) {
                return ['matched' => false];
            }

            $idUsuarioCoincidente = $idUsuario;
        }

        return $idUsuarioCoincidente === null
            ? ['matched' => false]
            : ['matched' => true, 'id_usuario' => $idUsuarioCoincidente];
    }

    public function capturar(string $terminalIdentifier): string
    {
        $resultado = $this->solicitar('/capturar', ['terminal' => $terminalIdentifier]);

        if (! is_string($resultado['sample'] ?? null) || $resultado['sample'] === '') {
            throw new BiometriaException('El puente biométrico no devolvió una muestra válida.');
        }

        return $resultado['sample'];
    }

    public function escuchar(?int $despuesDe): array
    {
        $resultado = $this->solicitar('/poll'.($despuesDe === null ? '' : '?after='.$despuesDe), [], 5);

        return [
            'seq' => (int) ($resultado['seq'] ?? 0),
            'sample' => is_string($resultado['sample'] ?? null) ? $resultado['sample'] : null,
        ];
    }

    public function iniciarEnrolar(Cliente $cliente, string $terminalIdentifier): void
    {
        $resultado = $this->solicitar('/enrolar/iniciar', [
            'cliente' => (string) $cliente->id,
            'terminal' => $terminalIdentifier,
        ], 5);

        if (($resultado['en_curso'] ?? false) !== true) {
            throw new BiometriaException('El puente biométrico no pudo iniciar el enrolamiento.');
        }
    }

    public function progresoEnrolar(Cliente $cliente): array
    {
        return $this->progresoEnrolamiento((string) $cliente->id);
    }

    public function progresoEnrolarEmpleado(Usuario $empleado): array
    {
        return $this->progresoEnrolamiento($this->identificadorEmpleado($empleado));
    }

    /**
     * @return array{muestras: int, total: int, en_curso: bool, estado: string, mensaje: string, error?: string}
     */
    private function progresoEnrolamiento(string $identificador): array
    {
        $resultado = $this->solicitar('/enrolar/progreso', ['cliente' => $identificador], 5);
        $muestras = $resultado['muestras'] ?? null;
        $total = $resultado['total'] ?? null;
        $enCurso = $resultado['en_curso'] ?? null;
        $estado = $resultado['estado'] ?? null;
        $mensaje = $resultado['mensaje'] ?? null;

        if (! is_int($muestras) || ! is_int($total) || ! is_bool($enCurso)
            || ! is_string($estado) || ! in_array($estado, ['inactivo', 'en_curso', 'completado', 'error'], true)
            || ! is_string($mensaje) || $total < 1 || $muestras < 0 || $muestras > $total) {
            throw new BiometriaException('El puente biométrico devolvió un progreso de enrolamiento inválido.');
        }

        $progreso = [
            'muestras' => $muestras,
            'total' => $total,
            'en_curso' => $enCurso,
            'estado' => $estado,
            'mensaje' => $mensaje,
        ];

        if (is_string($resultado['error'] ?? null)) {
            $progreso['error'] = $resultado['error'];
        }

        return $progreso;
    }

    public function finalizarEnrolar(Cliente $cliente): array
    {
        return $this->guardarCredencial($cliente, $this->plantillaEnrolada((string) $cliente->id));
    }

    public function iniciarEnrolarEmpleado(Usuario $empleado, string $terminalIdentifier): void
    {
        $resultado = $this->solicitar('/enrolar/iniciar', [
            'cliente' => $this->identificadorEmpleado($empleado),
            'terminal' => $terminalIdentifier,
        ], 5);

        if (($resultado['en_curso'] ?? false) !== true) {
            throw new BiometriaException('El puente biométrico no pudo iniciar el enrolamiento.');
        }
    }

    public function finalizarEnrolarEmpleado(Usuario $empleado): array
    {
        $plantilla = $this->plantillaEnrolada($this->identificadorEmpleado($empleado));

        return DB::transaction(function () use ($empleado, $plantilla): array {
            CredencialBiometricaEmpleado::query()
                ->where('usuario_id', $empleado->id)
                ->where('estado', true)
                ->update(['estado' => false]);

            $credencial = $empleado->credencialesBiometricas()->create([
                'proveedor' => 'digitalpersona',
                'id_externo' => Str::uuid()->toString(),
                'plantilla' => $plantilla,
                'estado' => true,
            ]);

            return [
                'id_usuario' => $empleado->id,
                'id_credencial' => $credencial->id,
                'proveedor' => $credencial->proveedor,
            ];
        });
    }

    public function enrolar(Cliente $cliente, string $terminalIdentifier): array
    {
        $resultado = $this->solicitar(
            '/enrolar',
            [
                'cliente' => (string) $cliente->id,
                'terminal' => $terminalIdentifier,
            ],
            (int) config('biometria.enroll_timeout'),
        );

        if (! is_string($resultado['template'] ?? null) || $resultado['template'] === '') {
            throw new BiometriaException('El puente biométrico no devolvió una plantilla válida.');
        }

        return $this->guardarCredencial($cliente, $resultado['template']);
    }

    /**
     * @return array{id_cliente: int, id_credencial: int, proveedor: string}
     */
    private function guardarCredencial(Cliente $cliente, string $plantilla): array
    {
        CredencialBiometrica::query()
            ->where('id_cliente', $cliente->id)
            ->where('estado', true)
            ->update(['estado' => false]);

        $credencial = (new CredencialBiometrica)->forceFill([
            'id_cliente' => $cliente->id,
            'proveedor' => 'digitalpersona',
            'id_externo' => Str::uuid()->toString(),
            'plantilla' => $plantilla,
            'estado' => true,
        ]);
        $credencial->save();

        return [
            'id_cliente' => $cliente->id,
            'id_credencial' => $credencial->id,
            'proveedor' => $credencial->proveedor,
        ];
    }

    private function plantillaEnrolada(string $identificador): string
    {
        $resultado = $this->solicitar('/enrolar/resultado', ['cliente' => $identificador], 5);

        if (! is_string($resultado['template'] ?? null) || $resultado['template'] === '') {
            throw new BiometriaException('El puente biométrico no devolvió una plantilla válida.');
        }

        return $resultado['template'];
    }

    private function identificadorEmpleado(Usuario $empleado): string
    {
        return 'empleado-'.$empleado->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function solicitar(string $ruta, array $payload, ?int $timeout = null): array
    {
        $timeout ??= (int) config('biometria.timeout');
        set_time_limit($timeout + 15);

        try {
            $respuesta = $this->cliente()->timeout($timeout)->post($ruta, $payload);
        } catch (ConnectionException) {
            throw new BiometriaException(
                'No se pudo conectar con el puente biométrico. Inicie biometria-java\ejecutar.ps1 y revise el lector.',
                503,
            );
        }

        if ($respuesta->failed()) {
            throw new BiometriaException(
                (string) ($respuesta->json('error') ?? 'El puente biométrico devolvió un error.'),
                $respuesta->status() === 422 ? 422 : 502,
            );
        }

        return $respuesta->json() ?? [];
    }

    private function cliente(): PendingRequest
    {
        return Http::baseUrl((string) config('biometria.url'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('biometria.connect_timeout'))
            ->timeout((int) config('biometria.timeout'));
    }
}
