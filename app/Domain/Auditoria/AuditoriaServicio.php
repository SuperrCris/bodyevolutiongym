<?php

namespace App\Domain\Auditoria;

use App\Models\Registros;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditoriaServicio
{
    public function registrar(
        string $accion,
        ?Model $registro = null,
        array $metadatos = [],
        ?int $usuarioId = null,
    ): Registros {
        return Registros::create([
            'usuario_id' => $usuarioId ?? Auth::id(),
            'accion' => $accion,
            'tipo' => $registro?->getMorphClass(),
            'id_registro' => $registro?->getKey(),
            'metadatos' => $metadatos,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
