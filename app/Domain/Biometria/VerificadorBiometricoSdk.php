<?php

namespace App\Domain\Biometria;

use App\Models\Cliente;
use App\Models\CredencialBiometrica;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
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
            $resultado = $this->solicitar('/verify', [
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

    public function capturar(string $terminalIdentifier): string
    {
        $resultado = $this->solicitar('/capture', ['terminal' => $terminalIdentifier]);

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

    public function enrolar(Cliente $cliente, string $terminalIdentifier): array
    {
        $resultado = $this->solicitar(
            '/enroll',
            ['terminal' => $terminalIdentifier],
            (int) config('biometria.enroll_timeout'),
        );

        if (! is_string($resultado['template'] ?? null) || $resultado['template'] === '') {
            throw new BiometriaException('El puente biométrico no devolvió una plantilla válida.');
        }

        CredencialBiometrica::query()
            ->where('id_cliente', $cliente->id)
            ->where('estado', true)
            ->update(['estado' => false]);

        $credencial = (new CredencialBiometrica)->forceFill([
            'id_cliente' => $cliente->id,
            'proveedor' => 'digitalpersona',
            'id_externo' => Str::uuid()->toString(),
            'plantilla' => $resultado['template'],
            'estado' => true,
        ]);
        $credencial->save();

        return [
            'id_cliente' => $cliente->id,
            'id_credencial' => $credencial->id,
            'proveedor' => $credencial->proveedor,
        ];
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
