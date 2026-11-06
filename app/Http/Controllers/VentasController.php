<?php

namespace App\Http\Controllers;

use App\Domain\Auditoria\AuditoriaServicio;
use App\Domain\Impresion\TicketeraException;
use App\Domain\Impresion\TicketeraServicio;
use App\Domain\Membresias\MembresiasServicio;
use App\Models\Pago;
use App\Models\TipoMembresia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VentasController extends Controller
{
    public function index(): View
    {
        return view('ventas.index', [
            'metodosPago' => Pago::METODOS_PAGO,
            'pagos' => Pago::query()
                ->with(['cliente', 'plan', 'membresia.plan'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(25),
        ]);
    }

    public function store(
        Request $request,
        MembresiasServicio $servicio,
        TicketeraServicio $ticketera,
    ): RedirectResponse {
        $datos = $request->validate([
            'tipo_membresia_id' => [
                'required',
                'integer',
                Rule::exists('tipo_membresias', 'id')->where('activo', true),
            ],
            'metodo_pago' => ['required', 'string', Rule::in(array_keys(Pago::METODOS_PAGO))],
            'nombre_comprador' => ['nullable', 'string', 'max:255'],
            'imprimir' => ['required', 'in:dos,uno,no'],
        ]);

        $pago = $servicio->registrarVentaSinCliente(
            plan: TipoMembresia::findOrFail($datos['tipo_membresia_id']),
            metodoPago: $datos['metodo_pago'],
            nombreComprador: $datos['nombre_comprador'] ?? null,
            vendedor: $request->user(),
        );

        $copias = match ($datos['imprimir']) {
            'dos' => 2,
            'uno' => 1,
            default => 0,
        };

        if ($copias > 0) {
            try {
                $impresora = $ticketera->imprimir($pago, $copias);
            } catch (TicketeraException $exception) {
                return to_route('pagos.recibo', $pago)->with('error', $exception->getMessage());
            }

            $confirmacion = $copias === 1 ? 'Un ticket enviado' : 'Dos tickets enviados';

            return to_route('clientes.index')
                ->with('success', "{$confirmacion} a la impresora predeterminada: {$impresora}.");
        }

        return to_route('clientes.index')->with('success', 'Venta de membresía registrada sin asignar a un cliente.');
    }

    public function actualizarMetodo(
        Request $request,
        Pago $pago,
        AuditoriaServicio $auditoria,
    ): RedirectResponse {
        $datos = $request->validate([
            'metodo_pago' => ['required', 'string', Rule::in(array_keys(Pago::METODOS_PAGO))],
        ]);

        $actualizado = DB::transaction(function () use ($pago, $datos, $request, $auditoria): bool {
            $pagoBloqueado = Pago::query()
                ->lockForUpdate()
                ->findOrFail($pago->getKey());
            $metodoAnterior = $pagoBloqueado->metodo_pago;

            if ($metodoAnterior === $datos['metodo_pago']) {
                return false;
            }

            $pagoBloqueado->metodo_pago = $datos['metodo_pago'];
            $pagoBloqueado->save();

            $auditoria->registrar(
                accion: 'pago.metodo_pago.actualizado',
                registro: $pagoBloqueado,
                metadatos: [
                    'metodo_anterior' => $metodoAnterior,
                    'metodo_nuevo' => $datos['metodo_pago'],
                ],
                usuarioId: $request->user()?->id,
            );

            return true;
        });

        return to_route('ventas.index')->with(
            'success',
            $actualizado
                ? 'Método de pago corregido correctamente.'
                : 'El pago ya tenía ese método; no se registró ningún cambio.',
        );
    }
}
