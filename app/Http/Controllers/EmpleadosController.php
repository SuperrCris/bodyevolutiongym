<?php

namespace App\Http\Controllers;

use App\Domain\Auditoria\AuditoriaServicio;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmpleadosController extends Controller
{
    public function index(): View
    {
        return view('empleados.index', [
            'empleados' => Usuario::query()
                ->withCount([
                    'credencialesBiometricas as huellas_activas_count' => fn ($query) => $query->where('estado', true),
                ])
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(20),
        ]);
    }

    public function store(Request $request, AuditoriaServicio $auditoria): RedirectResponse
    {
        $datos = $this->validatedData($request);
        $empleado = DB::transaction(function () use ($datos, $auditoria): Usuario {
            $empleado = Usuario::create($datos);
            $auditoria->registrar('empleado.creado', $empleado, [
                'username' => $empleado->username,
                'role' => $empleado->role,
            ]);

            return $empleado;
        });

        return to_route('empleados.index')->with('success', "La cuenta {$empleado->username} fue creada.");
    }

    public function update(
        Request $request,
        Usuario $empleado,
        AuditoriaServicio $auditoria,
    ): RedirectResponse {
        $datos = $this->validatedData($request, $empleado);
        $ultimoAdministrador = DB::transaction(function () use ($empleado, $datos, $auditoria): bool {
            $administradores = Usuario::query()
                ->where('role', Usuario::ROLE_ADMINISTRADOR)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $empleadoActual = Usuario::query()->findOrFail($empleado->id);

            if (
                $empleadoActual->esAdministrador()
                && $datos['role'] !== Usuario::ROLE_ADMINISTRADOR
                && $administradores->count() <= 1
            ) {
                return true;
            }

            $empleadoActual->update($datos);
            $auditoria->registrar('empleado.actualizado', $empleadoActual, [
                'username' => $empleadoActual->username,
                'role' => $empleadoActual->role,
            ]);

            return false;
        });

        if ($ultimoAdministrador) {
            return back()
                ->withErrors(['role' => 'Debe permanecer al menos una cuenta administradora.'])
                ->withInput();
        }

        return to_route('empleados.index')->with('success', 'La cuenta fue actualizada.');
    }

    /**
     * @return array{name: string, username: string, role: string, email: ?string, password?: string}
     */
    private function validatedData(Request $request, ?Usuario $empleado = null): array
    {
        if ($request->filled('username')) {
            $request->merge(['username' => Str::lower(trim($request->input('username')))]);
        }

        if ($request->filled('email')) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:40',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($empleado?->id),
            ],
            'role' => ['required', 'string', Rule::in([
                Usuario::ROLE_ADMINISTRADOR,
                Usuario::ROLE_RECEPCIONISTA,
            ])],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($empleado?->id),
            ],
            'password' => [
                $empleado === null ? 'required' : 'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        if (blank($datos['password'] ?? null)) {
            unset($datos['password']);
        }

        unset($datos['password_confirmation']);

        return $datos;
    }
}
