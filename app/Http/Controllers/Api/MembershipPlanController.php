<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoMembresia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipPlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TipoMembresia::query()->where('activo', true)->orderBy('duracion_dias')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $plan = TipoMembresia::create($request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'duracion_dias' => ['required', 'integer', 'min:1'],
            'precio' => ['required', 'numeric', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ]));

        return response()->json($plan, 201);
    }
}
