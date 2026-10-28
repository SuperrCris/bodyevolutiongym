<?php

namespace App\Domain\Biometria;

use App\Models\Cliente;
use App\Models\Usuario;

interface VerificadorBiometrico
{
    /**
     * @return array{matched: bool, id_cliente?: int, id_externo?: string}
     */
    public function verificar(string $captureToken, string $terminalIdentifier): array;

    /**
     * @return array{matched: bool, id_usuario?: int}
     */
    public function verificarEmpleado(string $captureToken, string $terminalIdentifier): array;

    public function capturar(string $terminalIdentifier): string;

    /**
     * @return array{seq: int, sample: ?string}
     */
    public function escuchar(?int $despuesDe): array;

    public function iniciarEnrolar(Cliente $cliente, string $terminalIdentifier): void;

    /**
     * @return array{muestras: int, total: int, en_curso: bool, estado: string, mensaje: string, error?: string}
     */
    public function progresoEnrolar(Cliente $cliente): array;

    public function finalizarEnrolar(Cliente $cliente): array;

    public function enrolar(Cliente $cliente, string $terminalIdentifier): array;

    public function iniciarEnrolarEmpleado(Usuario $empleado, string $terminalIdentifier): void;

    /**
     * @return array{muestras: int, total: int, en_curso: bool, estado: string, mensaje: string, error?: string}
     */
    public function progresoEnrolarEmpleado(Usuario $empleado): array;

    /**
     * @return array{id_usuario: int, id_credencial: int, proveedor: string}
     */
    public function finalizarEnrolarEmpleado(Usuario $empleado): array;
}
