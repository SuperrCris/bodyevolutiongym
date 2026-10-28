<?php

namespace App\Http\Controllers\Api;

use App\Domain\Auditoria\AuditoriaServicio;
use App\Http\Controllers\Controller;
use App\Models\TipoMembresia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MembershipPlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TipoMembresia::query()->where('activo', true)->orderBy('duracion_dias')->get());
    }

    public function store(Request $request, AuditoriaServicio $auditoria): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'duracion_dias' => ['required', 'integer', 'min:1', 'max:3650'],
            'precio' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'activo' => ['sometimes', 'boolean'],
        ]);
        $datos['activo'] = $request->boolean('activo', true);

        $plan = DB::transaction(function () use ($datos, $auditoria): TipoMembresia {
            $plan = TipoMembresia::create($datos);
            $auditoria->registrar('tipo_membresia.creado', $plan, $datos);

            return $plan;
        });

        return response()->json($plan, 201);
    }
}
