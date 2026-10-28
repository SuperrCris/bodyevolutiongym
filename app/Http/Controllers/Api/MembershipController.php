<?php

namespace App\Http\Controllers\Api;

use App\Domain\Membresias\MembresiasServicio;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\TipoMembresia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipController extends Controller
{
    public function renew(Request $request, Cliente $customer, MembresiasServicio $service): JsonResponse
    {
        $data = $request->validate([
            'membership_plan_id' => [
                'required',
                'integer',
                Rule::exists('tipo_membresias', 'id')->where('activo', true),
            ],
            'payment_method' => ['required', 'string', 'in:cash,card,transfer'],
            'reference' => ['nullable', 'string', 'max:100', 'unique:pagos,referencia'],
        ]);

        $membership = $service->renovar(
            $customer,
            TipoMembresia::findOrFail($data['membership_plan_id']),
            $data['payment_method'],
            $request->user(),
            $data['reference'] ?? null,
        );

        return response()->json($membership, 201);
    }
}
