<?php

namespace App\Domain\Biometria;

use App\Models\Cliente;
use App\Models\CredencialBiometrica;
use App\Models\CredencialBiometricaEmpleado;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VerificadorBiometricoSdk implements VerificadorBiometrico
{
    public function verificar(string $captureToken, string $terminalIdentifier): array
    {
        $coincidencia = null;

        CredencialBiometrica::query()
            ->where('estado', true)
            ->chunkById(
                $this->tamanoLote(),
                function (Collection $lote) use ($captureToken, $terminalIdentifier, &$coincidencia): bool {
                    $ids = $this->identificarLote($captureToken, $terminalIdentifier, $lote, false);

                    if ($ids === []) {
                        return true;
                    }

                    $coincidencia = $lote->firstWhere('id', $ids[0]);

                    return false;
                },
            );

        if ($coincidencia === null) {
            return ['matched' => false];
        }

        return [
            'matched' => true,
            'id_cliente' => $coincidencia->id_cliente,
            'id_externo' => $coincidencia->id_externo,
        ];
    }

    public function verificarEmpleado(string $captureToken, string $terminalIdentifier): array
    {
        $idsUsuario = [];

        CredencialBiometricaEmpleado::query()
            ->where('estado', true)
            ->chunkById(
                $this->tamanoLote(),
                function (Collection $lote) use ($captureToken, $terminalIdentifier, &$idsUsuario): bool {
                    foreach ($this->identificarLote($captureToken, $terminalIdentifier, $lote, true) as $id) {
                        $idsUsuario[(int) $lote->firstWhere('id', $id)->usuario_id] = true;
                    }

                    return count($idsUsuario) < 2;
                },
            );

        return count($idsUsuario) === 1
            ? ['matched' => true, 'id_usuario' => array_key_first($idsUsuario)]
            : ['matched' => false];
    }

    /**
     * Envía el lote al puente en una sola petición y devuelve los ids de credenciales que coinciden.
     *
     * @param  Collection<int, CredencialBiometrica|CredencialBiometricaEmpleado>  $lote
     * @return list<int>
     */
    private function identificarLote(string $muestra, string $terminalIdentifier, Collection $lote, bool $todas): array
    {
        $candidatos = $lote
            ->filter(fn ($credencial): bool => is_string($credencial->plantilla) && $credencial->plantilla !== '')
            ->map(fn ($credencial): array => [
                'id' => (string) $credencial->getKey(),
                'template' => $credencial->plantilla,
            ])
            ->values()
            ->all();

        if ($candidatos === []) {
            return [];
        }

        $resultado = $this->solicitar('/identificar', [
            'sample' => $muestra,
            'terminal' => $terminalIdentifier,
            'todas' => $todas,
            'candidatos' => $candidatos,
        ]);
        $coincidencias = $resultado['coincidencias'] ?? null;

        if (! is_array($coincidencias)) {
            throw new BiometriaException('El puente biométrico devolvió una identificación inválida.');
        }

        if ((int) ($resultado['invalidas'] ?? 0) > 0) {
            Log::warning('El puente biométrico descartó plantillas inválidas durante la identificación.', [
                'invalidas' => (int) $resultado['invalidas'],
            ]);
        }

        return array_map('intval', $coincidencias);
    }

    private function tamanoLote(): int
    {
        return max(1, (int) config('biometria.lote_identificacion'));
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
