<?php

namespace App\Domain\Membresias;

use App\Domain\Auditoria\AuditoriaServicio;

use App\Models\Cliente;
use App\Models\Membresia;
use App\Models\TipoMembresia;
use App\Models\Pago;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MembresiasServicio
{
    public function renovar(
        Cliente $cliente,
        TipoMembresia $plan,
        string $metodoPago,
        ?Usuario $vendedor = null,
        ?string $referencia = null,
        ?AuditoriaServicio $auditoria = null,
    ): Membresia {
        if (! $plan->activo) {
            throw new InvalidArgumentException('El plan de membresía no está activo.');
        }

        return DB::transaction(function () use ($cliente, $plan, $metodoPago, $vendedor, $referencia, $auditoria): Membresia {
            $clienteActual = $cliente->membresias()
                ->where('estado', 'activo')
                ->whereDate('finalizacion', '>=', now()->toDateString())
                ->latest('finalizacion')
                ->lockForUpdate()
                ->first();

            $empieza = $clienteActual?->finalizacion?->copy()->addDay() ?? now()->startOfDay();
            $termina = $empieza->copy()->addDays($plan->duracion_dias - 1);

            $membresiaActual = $cliente->membresias()->create([
                'tipo_membresia_id' => $plan->id,
                'comienzo' => $empieza,
                'finalizacion' => $termina,
                'estado' => 'active',
            ]);

            Pago::create([
                'cliente_id' => $cliente->id,
                'membresia_id' => $membresiaActual->id,
                'user_id' => $vendedor?->id,
                'monto' => $plan->precio,
                'metodo_pago' => $metodoPago,
                'referencia' => $referencia,
            ]);

            ($auditoria ?? app(AuditoriaServicio::class))->registrar(
                accion: 'membresia.renovada',
                registro: $membresiaActual,
                metadatos: [
                    'plan_id' => $plan->id,
                    'cliente_id' => $cliente->id,
                    'monto' => $plan->precio,
                ],
                usuarioId: $vendedor?->id,
            );

            return $membresiaActual->load(['cliente', 'tipo_membresia']);
        });
    }



public function crearClienteConMembresia(
    array $datosCliente,
    TipoMembresia $plan,
    string $metodoPago,
    ?Usuario $usuario = null,
    ?string $referencia = null,
    ?AuditoriaServicio $auditoria = null,
): Membresia {
    return DB::transaction(function () use (
        $datosCliente,
        $plan,
        $metodoPago,
        $usuario,
        $referencia,
        $auditoria,
    ): Membresia {
        $cliente = Cliente::create($datosCliente);

        $membresia = $cliente->membresias()->create([
            'tipo_membresia_id' => $plan->id,
            'comienzo' => now(),
            'finalizacion' => now()->addDays($plan->duracion_dias - 1),
            'estado' => 'active',
        ]);

        $membresia->pagos()->create([
            'id_cliente' => $cliente->id,
            'usuario_id' => $usuario?->id,
            'monto' => $plan->precio,
            'metodo_pago' => $metodoPago,
            'referencia' => $referencia,
        ]);

        ($auditoria ?? app(AuditoriaServicio::class))->registrar(
            accion: 'membresia.creada',
            registro: $membresia,
            metadatos: [
                'plan_id' => $plan->id,
                'cliente_id' => $cliente->id,
                'monto' => $plan->precio,
            ],
            usuarioId: $usuario?->id,
        );

        return $membresia;
    });
}

}
