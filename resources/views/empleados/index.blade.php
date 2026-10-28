@extends('layouts.app')

@section('title', 'Empleados | Body Evolution Gym')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal-300">Administración</p>
                <h1 class="mt-2 text-3xl font-black text-white">Empleados y accesos</h1>
                <p class="mt-2 text-sm text-slate-400">Crea cuentas, asigna roles y restablece contraseñas.</p>
            </div>
        </div>

        <section class="panel p-5 shadow-xl sm:p-7">
            <h2 class="text-lg font-bold text-white">Crear cuenta</h2>
            <form method="POST" action="{{ route('empleados.store') }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @csrf
                <label class="block">
                    <span class="field-label">Nombre completo</span>
                    <input name="name" value="{{ old('name') }}" required maxlength="255" class="field-control" autocomplete="name">
                </label>
                <label class="block">
                    <span class="field-label">Usuario de acceso</span>
                    <input name="username" value="{{ old('username') }}" required minlength="3" maxlength="40" pattern="[A-Za-z0-9._-]+" class="field-control" autocomplete="username">
                </label>
                <label class="block">
                    <span class="field-label">Rol</span>
                    <select name="role" required class="field-control">
                        <option value="recepcionista">Recepcionista</option>
                        <option value="administrador" @selected(old('role') === 'administrador')>Administrador</option>
                    </select>
                </label>
                <label class="block">
                    <span class="field-label">Correo (opcional)</span>
                    <input type="email" name="email" value="{{ old('email') }}" maxlength="255" class="field-control" autocomplete="email">
                </label>
                <label class="block">
                    <span class="field-label">Contraseña inicial</span>
                    <input type="password" name="password" required minlength="8" class="field-control" autocomplete="new-password">
                </label>
                <label class="block">
                    <span class="field-label">Confirmar contraseña</span>
                    <input type="password" name="password_confirmation" required minlength="8" class="field-control" autocomplete="new-password">
                </label>
                <div class="md:col-span-2 xl:col-span-3">
                    <button class="button-primary px-5 py-3 transition">Crear empleado</button>
                </div>
            </form>
        </section>

        <section class="panel overflow-hidden shadow-xl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-4 sm:px-7">
                <h2 class="text-lg font-bold text-white">Cuentas registradas</h2>
                <span class="text-sm text-slate-400">{{ $empleados->total() }} cuentas</span>
            </div>
            <div class="divide-y divide-white/5">
                @forelse ($empleados as $empleado)
                    <details class="group px-5 py-4 sm:px-7">
                        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3">
                            <span>
                                <span class="block font-semibold text-white">{{ $empleado->name }}</span>
                                <span class="text-sm text-slate-400">{{ $empleado->username }} · {{ $empleado->email ?: 'Sin correo' }}</span>
                            </span>
                            <span class="flex items-center gap-3">
                                <span class="rounded-full border border-teal-300/20 bg-teal-300/10 px-3 py-1 text-xs font-semibold text-teal-200">{{ $empleado->role === 'administrador' ? 'Administrador' : 'Recepcionista' }}</span>
                                <span class="text-sm text-slate-400 group-open:rotate-180">⌄</span>
                            </span>
                        </summary>
                        <form method="POST" action="{{ route('empleados.update', $empleado) }}" class="mt-5 grid gap-4 rounded-2xl border border-white/10 bg-slate-950/50 p-4 md:grid-cols-2 xl:grid-cols-3">
                            @csrf
                            @method('PUT')
                            <label class="block">
                                <span class="field-label">Nombre completo</span>
                                <input name="name" value="{{ $empleado->name }}" required maxlength="255" class="field-control-solid">
                            </label>
                            <label class="block">
                                <span class="field-label">Usuario</span>
                                <input name="username" value="{{ $empleado->username }}" required minlength="3" maxlength="40" pattern="[A-Za-z0-9._-]+" class="field-control-solid">
                            </label>
                            <label class="block">
                                <span class="field-label">Rol</span>
                                <select name="role" required class="field-control-solid">
                                    <option value="recepcionista" @selected($empleado->role === 'recepcionista')>Recepcionista</option>
                                    <option value="administrador" @selected($empleado->role === 'administrador')>Administrador</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="field-label">Correo (opcional)</span>
                                <input type="email" name="email" value="{{ $empleado->email }}" maxlength="255" class="field-control-solid">
                            </label>
                            <label class="block">
                                <span class="field-label">Nueva contraseña (opcional)</span>
                                <input type="password" name="password" minlength="8" class="field-control-solid" autocomplete="new-password">
                            </label>
                            <label class="block">
                                <span class="field-label">Confirmar contraseña</span>
                                <input type="password" name="password_confirmation" minlength="8" class="field-control-solid" autocomplete="new-password">
                            </label>
                            <div class="md:col-span-2 xl:col-span-3">
                                <button class="rounded-xl border border-teal-300/30 px-4 py-2.5 text-sm font-semibold text-teal-200 transition hover:bg-teal-300/10">Guardar cambios</button>
                            </div>
                        </form>
                        <section class="mt-4 rounded-2xl border border-white/10 bg-slate-950/50 p-4" data-enrolamiento-empleado>
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h3 class="font-semibold text-white">Acceso con huella</h3>
                                    <p class="mt-1 text-sm text-slate-400" data-estado-huella>
                                        {{ $empleado->huellas_activas_count > 0 ? 'Huella registrada' : 'Sin huella registrada' }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="flex items-center gap-2" role="group" aria-label="Progreso de enrolamiento de huella">
                                        @for ($captura = 1; $captura <= 4; $captura++)
                                            <span data-paso-huella class="size-3 rounded-full border border-white/20 bg-transparent transition-colors" aria-hidden="true"></span>
                                        @endfor
                                    </div>
                                    <button
                                        type="button"
                                        data-enrolar-huella
                                        data-iniciar-url="{{ route('empleados.huella.iniciar', $empleado) }}"
                                        data-progreso-url="{{ route('empleados.huella.progreso', $empleado) }}"
                                        data-finalizar-url="{{ route('empleados.huella.finalizar', $empleado) }}"
                                        class="rounded-xl border border-teal-300/30 px-4 py-2.5 text-sm font-semibold text-teal-200 transition hover:bg-teal-300/10 disabled:cursor-wait disabled:opacity-60"
                                    >
                                        Registrar o actualizar huella
                                    </button>
                                </div>
                            </div>
                            <p class="mt-3 text-sm text-slate-400" data-feedback-huella role="status" aria-live="polite">
                                El empleado deberá colocar el mismo dedo cuatro veces.
                            </p>
                        </section>
                    </details>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-400">Todavía no hay cuentas de empleados.</p>
                @endforelse
            </div>
            <div class="border-t border-white/10 px-5 py-4 sm:px-7">{{ $empleados->links() }}</div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-enrolamiento-empleado]').forEach((panel) => {
            const button = panel.querySelector('[data-enrolar-huella]');
            const feedback = panel.querySelector('[data-feedback-huella]');
            const estado = panel.querySelector('[data-estado-huella]');
            const pasos = [...panel.querySelectorAll('[data-paso-huella]')];

            const actualizarProgreso = (capturas) => {
                pasos.forEach((paso, index) => {
                    const aceptada = index < capturas;
                    paso.classList.toggle('border-teal-300', aceptada);
                    paso.classList.toggle('bg-teal-300', aceptada);
                    paso.classList.toggle('border-white/20', !aceptada);
                    paso.classList.toggle('bg-transparent', !aceptada);
                });
            };

            const solicitarJson = async (url, method = 'GET') => {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

                if (!csrf) {
                    throw new Error('No se pudo validar la sesión. Recarga la página e inténtalo de nuevo.');
                }

                const response = await fetch(url, {
                    method,
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                });
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message ?? 'No se pudo completar el enrolamiento de huella.');
                }

                return data;
            };

            button.addEventListener('click', async () => {
                button.disabled = true;
                actualizarProgreso(0);
                feedback.textContent = 'Iniciando el lector biométrico…';

                try {
                    await solicitarJson(button.dataset.iniciarUrl, 'POST');
                    const limite = Date.now() + 90000;
                    let completado = false;

                    while (Date.now() < limite) {
                        const progreso = await solicitarJson(button.dataset.progresoUrl);
                        actualizarProgreso(progreso.muestras);
                        feedback.textContent = progreso.mensaje;

                        if (progreso.estado === 'error') {
                            throw new Error(progreso.error || progreso.mensaje || 'El lector no pudo registrar la huella.');
                        }

                        if (progreso.estado === 'completado') {
                            await solicitarJson(button.dataset.finalizarUrl, 'POST');
                            estado.textContent = 'Huella registrada';
                            feedback.textContent = 'Huella registrada correctamente para el acceso del empleado.';
                            completado = true;
                            break;
                        }

                        if (progreso.estado === 'inactivo') {
                            throw new Error('El puente biométrico perdió el enrolamiento. Inténtalo de nuevo.');
                        }

                        await new Promise((resolve) => window.setTimeout(resolve, 300));
                    }

                    if (!completado) {
                        throw new Error('Se agotó el tiempo de espera del lector. Inténtalo de nuevo.');
                    }
                } catch (error) {
                    feedback.textContent = error instanceof Error
                        ? error.message
                        : 'No se pudo completar el enrolamiento de huella.';
                } finally {
                    button.disabled = false;
                }
            });
        });
    </script>
@endpush
