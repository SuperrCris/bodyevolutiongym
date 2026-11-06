<?php

namespace App\Domain\Membresias;

use App\Domain\Auditoria\AuditoriaServicio;
use App\Models\Cliente;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\TipoMembresia;
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
                ->where('estado', Membresia::ESTADO_ACTIVA)
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
                'estado' => Membresia::ESTADO_ACTIVA,
            ]);

            Pago::create([
                'id_cliente' => $cliente->id,
                'membresia_id' => $membresiaActual->id,
                'tipo_membresia_id' => $plan->id,
                'usuario_id' => $vendedor?->id,
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

            return $membresiaActual->load(['cliente', 'plan']);
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
        if (! $plan->activo) {
            throw new InvalidArgumentException('El plan de membresía no está activo.');
        }

        return DB::transaction(function () use (
            $datosCliente,
            $plan,
            $metodoPago,
            $usuario,
            $referencia,
            $auditoria,
        ): Membresia {
            $cliente = Cliente::create($datosCliente);
            $comienzo = now()->startOfDay();

            $membresia = $cliente->membresias()->create([
                'tipo_membresia_id' => $plan->id,
                'comienzo' => $comienzo,
                'finalizacion' => $comienzo->copy()->addDays($plan->duracion_dias - 1),
                'estado' => Membresia::ESTADO_ACTIVA,
            ]);

            $membresia->pagos()->create([
                'id_cliente' => $cliente->id,
                'tipo_membresia_id' => $plan->id,
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

            return $membresia->load(['cliente', 'plan']);
        });
    }

    public function registrarVentaSinCliente(
        TipoMembresia $plan,
        string $metodoPago,
        ?string $nombreComprador = null,
        ?Usuario $vendedor = null,
        ?AuditoriaServicio $auditoria = null,
    ): Pago {
        if (! $plan->activo) {
            throw new InvalidArgumentException('El plan de membresía no está activo.');
        }

        return DB::transaction(function () use ($plan, $metodoPago, $nombreComprador, $vendedor, $auditoria): Pago {
            $pago = Pago::create([
                'tipo_membresia_id' => $plan->id,
                'nombre_comprador' => $nombreComprador,
                'usuario_id' => $vendedor?->id,
                'monto' => $plan->precio,
                'metodo_pago' => $metodoPago,
            ]);

            ($auditoria ?? app(AuditoriaServicio::class))->registrar(
                accion: 'membresia.venta_sin_cliente',
                registro: $pago,
                metadatos: [
                    'plan_id' => $plan->id,
                    'monto' => $plan->precio,
                ],
                usuarioId: $vendedor?->id,
            );

            return $pago->load('plan');
        });
    }
}
