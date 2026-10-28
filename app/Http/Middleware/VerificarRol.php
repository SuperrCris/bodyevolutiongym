<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();
        $esAdministrador = $usuario?->role === 'administrador';
        $tieneRol = $usuario !== null && in_array($usuario->role, $roles, true);

        abort_unless($esAdministrador || $tieneRol, 403);

        return $next($request);
    }
}
