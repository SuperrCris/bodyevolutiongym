<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customers = Cliente::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = $request->string('search')->toString();
                $query->where(function ($query) use ($term): void {
                    $query->where('nombre', 'like', "%{$term}%")
                        ->orWhere('apellido', 'like', "%{$term}%")
                        ->orWhere('documento', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', 'unique:clientes,email'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'documento' => ['nullable', 'string', 'max:50', 'unique:clientes,documento'],
            'fecha_nacimiento' => ['nullable', 'date'],
        ]);
        $customer = Cliente::create($data);

        return response()->json($customer, 201);
    }

    public function show(Cliente $customer): JsonResponse
    {
        return response()->json($customer->load(['membresias.plan', 'credencialesBiometricas']));
    }
}
