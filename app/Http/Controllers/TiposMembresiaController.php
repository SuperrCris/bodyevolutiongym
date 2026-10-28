<?php

namespace App\Http\Controllers;

use App\Domain\Auditoria\AuditoriaServicio;
use App\Models\TipoMembresia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TiposMembresiaController extends Controller
{
    public function index(): View
    {
        return view('tipos-membresia.index', [
            'tiposMembresia' => TipoMembresia::query()
                ->orderBy('duracion_dias')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request, AuditoriaServicio $auditoria): RedirectResponse
    {
        $datos = $this->validatedData($request);
        $tipoMembresia = DB::transaction(function () use ($datos, $auditoria): TipoMembresia {
            $tipoMembresia = TipoMembresia::create($datos);
            $auditoria->registrar('tipo_membresia.creado', $tipoMembresia, $datos);

            return $tipoMembresia;
        });

        return to_route('tipos-membresia.index')
            ->with('success', "La membresía {$tipoMembresia->nombre} fue creada.");
    }

    public function update(
        Request $request,
        TipoMembresia $tipoMembresia,
        AuditoriaServicio $auditoria,
    ): RedirectResponse {
        $datos = $this->validatedData($request);

        DB::transaction(function () use ($tipoMembresia, $datos, $auditoria): void {
            $tipoMembresia->update($datos);
            $auditoria->registrar('tipo_membresia.actualizado', $tipoMembresia, $datos);
        });

        return to_route('tipos-membresia.index')
            ->with('success', "La membresía {$tipoMembresia->nombre} fue actualizada.");
    }

    /**
     * @return array{nombre: string, duracion_dias: int, precio: string, activo: bool}
     */
    private function validatedData(Request $request): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'duracion_dias' => ['required', 'integer', 'min:1', 'max:3650'],
            'precio' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'activo' => ['required', 'boolean'],
        ]);

        $datos['duracion_dias'] = (int) $datos['duracion_dias'];
        $datos['precio'] = (string) $datos['precio'];
        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}
