<?php

namespace App\Http\Controllers;

use App\Models\Registros;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrosController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'accion' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return view('registros.index', [
            'registros' => Registros::query()
                ->with('usuario:id,name,username')
                ->when($filtros['accion'] ?? null, fn ($query, string $accion) => $query->where('accion', 'like', "%{$accion}%"))
                ->when($filtros['desde'] ?? null, fn ($query, string $desde) => $query->whereDate('created_at', '>=', $desde))
                ->when($filtros['hasta'] ?? null, fn ($query, string $hasta) => $query->whereDate('created_at', '<=', $hasta))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString(),
            'filtros' => $filtros,
        ]);
    }
}
