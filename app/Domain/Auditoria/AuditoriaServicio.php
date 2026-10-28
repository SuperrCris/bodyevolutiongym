<?php

namespace App\Domain\Auditoria;

use App\Models\Membresia;
use App\Models\Registros;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditoriaServicio
{
    public function registrarCambioFinalizacionMembresia(
        Membresia $membresia,
        string $finalizacionAnterior,
        string $finalizacionNueva,
        Usuario $administrador,
    ): Registros {
        return $this->registrar(
            accion: 'membresia.vigencia.actualizada',
            registro: $membresia,
            metadatos: [
                'finalizacion_anterior' => $finalizacionAnterior,
                'finalizacion_nueva' => $finalizacionNueva,
            ],
            usuarioId: $administrador->id,
        );
    }

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
