<?php

namespace App\Http\Controllers;

use App\Domain\Auditoria\AuditoriaServicio;
use App\Models\Egreso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EgresosController extends Controller
{
    public function index(): View
    {
        return view('egresos.index', [
            'egresos' => Egreso::query()
                ->with('usuario:id,name')
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->paginate(15),
        ]);
    }

    public function store(Request $request, AuditoriaServicio $auditoria): RedirectResponse
    {
        $datos = $request->validate([
            'concepto' => ['required', 'string', 'max:160'],
            'categoria' => ['required', 'string', 'max:80'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'metodo_pago' => ['nullable', 'string', 'in:efectivo,tarjeta,transferencia,otro'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ]);

        $egreso = DB::transaction(function () use ($datos, $request, $auditoria): Egreso {
            $egreso = Egreso::create([
                ...$datos,
                'usuario_id' => $request->user()->id,
            ]);

            $auditoria->registrar('egreso.creado', $egreso, [
                'categoria' => $egreso->categoria,
                'monto' => $egreso->monto,
            ]);

            return $egreso;
        });

        return to_route('egresos.index')->with('success', "Egreso de {$egreso->monto} registrado.");
    }
}
