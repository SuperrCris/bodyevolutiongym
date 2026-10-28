<?php

namespace App\Http\Controllers;

use App\Domain\Auditoria\AuditoriaServicio;
use App\Domain\Biometria\BiometriaException;
use App\Domain\Biometria\VerificadorBiometrico;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);
        $identificador = trim($datos['username']);
        $username = Str::lower($identificador);
        $usuario = Usuario::query()->where('username', $username)->first();

        if ($usuario === null) {
            $usuariosPorNombre = Usuario::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower($identificador)])
                ->limit(2)
                ->get();

            if ($usuariosPorNombre->count() === 1) {
                $usuario = $usuariosPorNombre->first();
            }
        }

        if ($usuario === null || ! Hash::check($datos['password'], $usuario->password)) {
            return back()
                ->withErrors(['username' => 'Las credenciales no son válidas.'])
                ->onlyInput('username');
        }

        Auth::login($usuario, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('clientes.index'))
            ->with('success', 'Sesión iniciada correctamente.');
    }

    public function fingerprint(
        Request $request,
        VerificadorBiometrico $verifier,
        AuditoriaServicio $auditoria,
    ): RedirectResponse {
        try {
            $muestra = $verifier->capturar((string) config('biometria.terminal'));
            $resultado = $verifier->verificarEmpleado($muestra, (string) config('biometria.terminal'));
        } catch (BiometriaException $exception) {
            return back()->withErrors(['fingerprint' => $exception->getMessage()]);
        }

        $idUsuario = $resultado['id_usuario'] ?? null;

        if (($resultado['matched'] ?? false) !== true || ! is_int($idUsuario)) {
            return back()->withErrors(['fingerprint' => 'No se pudo identificar una huella de empleado registrada.']);
        }

        $empleado = Usuario::query()->find($idUsuario);

        if ($empleado === null) {
            return back()->withErrors(['fingerprint' => 'No se pudo identificar una huella de empleado registrada.']);
        }

        Auth::login($empleado);
        $request->session()->regenerate();
        $auditoria->registrar('autenticacion.huella', $empleado, ['metodo' => 'huella']);

        return redirect()->intended(route('clientes.index'))
            ->with('success', 'Sesión iniciada correctamente.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
