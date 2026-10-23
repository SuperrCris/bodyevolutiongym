<?php

namespace App\Http\Controllers;

use App\Domain\Membresias\MembresiasServicio;
use App\Models\Cliente;
use App\Models\TipoMembresia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientesController extends Controller
{
    public function index(Request $request): View
    {
        $clientes = Cliente::query()
            ->with([
                'membresias' => fn ($query) => $query->latest('finalizacion')->with('plan'),
                'credencialesBiometricas' => fn ($query) => $query->where('estado', true),
            ])
            ->when($request->filled('buscar'), function ($query) use ($request): void {
                $termino = $request->string('buscar')->toString();
                $query->where(function ($query) use ($termino): void {
                    $query->where('nombre', 'like', "%{$termino}%")
                        ->orWhere('apellido', 'like', "%{$termino}%")
                        ->orWhere('documento', 'like', "%{$termino}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('clientes.index', [
            'clientes' => $clientes,
            'planes' => TipoMembresia::query()->where('activo', true)->orderBy('duracion_dias')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Cliente::create($this->validatedData($request));

        return to_route('clientes.index')->with('success', 'Cliente creado correctamente.');
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($this->validatedData($request, $cliente));

        return to_route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    public function renew(
        Request $request,
        Cliente $cliente,
        MembresiasServicio $servicio,
    ): RedirectResponse {
        $data = $request->validate([
            'tipo_membresia_id' => ['required', 'integer', 'exists:tipo_membresias,id'],
            'metodo_pago' => ['required', 'string', 'in:efectivo,tarjeta,transferencia'],
            'referencia' => ['nullable', 'string', 'max:100', 'unique:pagos,referencia'],
        ]);

        $metodos = [
            'efectivo' => 'cash',
            'tarjeta' => 'card',
            'transferencia' => 'transfer',
        ];

        $servicio->renovar(
            $cliente,
            TipoMembresia::findOrFail($data['tipo_membresia_id']),
            $metodos[$data['metodo_pago']],
            $request->user(),
            $data['referencia'] ?? null,
        );

        return to_route('clientes.index')->with('success', 'Membresía renovada correctamente.');
    }

    public function resumen(): JsonResponse
    {
        return response()->json([
            'clientes' => Cliente::query()->count(),
            'membresias_activas' => Cliente::query()
                ->whereHas('membresias', fn ($query) => $query
                    ->where('estado', 'activa')
                    ->whereDate('finalizacion', '>=', now()->toDateString()))
                ->count(),
            'ultimos_clientes' => Cliente::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'nombre', 'apellido', 'created_at']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Cliente $cliente = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('clientes', 'email')->ignore($cliente?->id),
            ],
            'telefono' => ['nullable', 'string', 'max:30'],
            'documento' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('clientes', 'documento')->ignore($cliente?->id),
            ],
            'fecha_nacimiento' => ['nullable', 'date'],
        ]);
    }
}
