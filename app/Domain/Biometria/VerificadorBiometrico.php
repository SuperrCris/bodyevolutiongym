<?php

namespace App\Domain\Biometria;

use App\Models\Cliente;

interface VerificadorBiometrico
{
    /**
     * @return array{matched: bool, id_cliente?: int, id_externo?: string}
     */
    public function verificar(string $captureToken, string $terminalIdentifier): array;

    public function capturar(string $terminalIdentifier): string;

    /**
     * @return array{seq: int, sample: ?string}
     */
    public function escuchar(?int $despuesDe): array;

    public function enrolar(Cliente $cliente, string $terminalIdentifier): array;
}
