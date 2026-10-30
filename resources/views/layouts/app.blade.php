<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Body Evolution Gym')</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top_right,_rgba(20,184,166,0.14),_transparent_34%),linear-gradient(135deg,#020617_0%,#0f172a_52%,#111827_100%)]">
        <header class="border-b border-white/10 bg-slate-950/70 backdrop-blur">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-4">
                <a href="{{ route('clientes.index') }}" class="flex items-center gap-3">
                    <img src="{{ asset('images/logo.png') }}" alt="Body Evolution Gym" class="h-10 w-auto">
                </a>
                <nav class="flex flex-wrap items-center gap-2 text-sm text-slate-300">
                    <a href="{{ route('clientes.index') }}" class="rounded-xl px-3 py-2 transition hover:bg-white/5 hover:text-teal-200">Clientes</a>
                    <a href="{{ route('reportes.index') }}" class="rounded-xl px-3 py-2 transition hover:bg-white/5 hover:text-teal-200">Reportes</a>
                    <a href="{{ route('ajustes.index') }}" class="rounded-xl px-3 py-2 transition hover:bg-white/5 hover:text-teal-200">Ajustes</a>
                    @if (auth()->user()->esAdministrador())
                        <a href="{{ route('tipos-membresia.index') }}" class="rounded-xl px-3 py-2 transition hover:bg-white/5 hover:text-teal-200">Membresías</a>
                        <a href="{{ route('empleados.index') }}" class="rounded-xl px-3 py-2 transition hover:bg-white/5 hover:text-teal-200">Empleados</a>
                        <a href="{{ route('registros.index') }}" class="rounded-xl px-3 py-2 transition hover:bg-white/5 hover:text-teal-200">Auditoría</a>
                    @endif
                </nav>
                <div class="flex items-center gap-4">
                    <span class="hidden text-sm text-slate-300 sm:block">{{ auth()->user()->name }} · {{ auth()->user()->role }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:border-teal-300/50 hover:text-teal-200">Salir</button>
                    </form>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-7xl px-6 py-8">
            @if (session('success'))
                <div data-toast class="mb-6 flex items-center gap-3 rounded-2xl border border-teal-300/20 bg-teal-400/10 px-4 py-3 text-sm text-teal-100">
                    <span class="grid size-7 place-items-center rounded-full bg-teal-300 font-bold text-slate-950">✓</span>
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-6 rounded-2xl border border-rose-300/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-100">
                    <p class="font-semibold">Revisa los datos enviados.</p>
                    <ul class="mt-2 list-inside list-disc text-rose-200/80">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    <div
        id="toastHuella"
        data-clientes-url="{{ route('clientes.index') }}"
        data-duracion-segundos="{{ auth()->user()->duracion_notificacion_segundos }}"
        aria-live="polite"
        aria-relevant="additions"
        class="fixed bottom-4 left-4 z-50 flex max-h-[calc(100vh-2rem)] w-[calc(100vw-2rem)] flex-col justify-end gap-3 overflow-y-auto sm:bottom-6 sm:left-6 sm:w-96"
    ></div>
    <script>
        (() => {
            const contenedor = document.getElementById('toastHuella');
            const duracionNotificacion = Number(contenedor.dataset.duracionSegundos) * 1000;
            let ultimo = null;
            let caido = false;

            function iniciarTemporizador(toast, barra) {
                let temporizador = null;
                const pausas = new Set();

                function iniciar() {
                    if (temporizador !== null || pausas.size > 0) return;

                    barra.style.transition = `transform ${duracionNotificacion}ms linear`;
                    barra.style.transform = 'scaleX(0)';
                    temporizador = window.setTimeout(cerrar, duracionNotificacion);
                }

                function cerrar() {
                    if (temporizador !== null) {
                        window.clearTimeout(temporizador);
                        temporizador = null;
                    }

                    toast.remove();
                }

                function reiniciarYpausar(motivo) {
                    pausas.add(motivo);
                    if (temporizador !== null) {
                        window.clearTimeout(temporizador);
                        temporizador = null;
                    }

                    barra.style.transition = 'none';
                    barra.style.transform = 'scaleX(1)';
                    barra.getBoundingClientRect();
                }

                function reanudar(motivo) {
                    pausas.delete(motivo);
                    iniciar();
                }

                toast.addEventListener('mouseenter', () => reiniciarYpausar('puntero'));
                toast.addEventListener('mouseleave', () => reanudar('puntero'));
                toast.addEventListener('focusin', () => reiniciarYpausar('foco'));
                toast.addEventListener('focusout', (evento) => {
                    if (!toast.contains(evento.relatedTarget)) reanudar('foco');
                });

                iniciar();

                return cerrar;
            }

            function mostrar(titulo, detalle, tono, cliente = null) {
                const estilos = {
                    ok: 'border-teal-300/30 bg-teal-950/95 text-teal-100',
                    aviso: 'border-amber-300/30 bg-amber-950/95 text-amber-100',
                    error: 'border-rose-300/30 bg-rose-950/95 text-rose-100',
                };
                const toast = document.createElement('div');
                toast.className = `animate-[toast-rise_180ms_ease-out] motion-reduce:animate-none relative overflow-hidden rounded-2xl border px-4 py-3 pb-5 shadow-2xl backdrop-blur transition ${estilos[tono]}`;
                const cerrar = document.createElement('button');
                cerrar.type = 'button';
                cerrar.className = 'absolute right-2 top-2 grid size-8 place-items-center rounded-lg text-xl leading-none opacity-75 transition hover:bg-white/10 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current';
                cerrar.setAttribute('aria-label', 'Cerrar notificación');
                cerrar.title = 'Cerrar notificación';
                cerrar.textContent = '×';
                toast.appendChild(cerrar);

                const cuerpo = document.createElement('div');
                cuerpo.className = 'flex items-start gap-3 pr-7';

                if (cliente !== null) {
                    const avatar = document.createElement('div');
                    avatar.className = 'grid size-12 shrink-0 place-items-center overflow-hidden rounded-full bg-slate-800 text-sm font-bold text-teal-200 ring-1 ring-white/15';

                    const iniciales = (cliente.nombre ?? '')
                        .trim()
                        .split(/\s+/)
                        .slice(0, 2)
                        .map((parte) => Array.from(parte)[0] ?? '')
                        .join('')
                        .toLocaleUpperCase() || 'CL';

                    if (cliente.foto) {
                        const foto = document.createElement('img');
                        foto.src = cliente.foto;
                        foto.alt = `Foto de ${cliente.nombre}`;
                        foto.className = 'size-full object-cover';
                        foto.addEventListener('error', () => {
                            foto.remove();
                            avatar.setAttribute('role', 'img');
                            avatar.setAttribute('aria-label', `Foto no disponible de ${cliente.nombre}`);
                            avatar.textContent = iniciales;
                        }, { once: true });
                        avatar.appendChild(foto);
                    } else {
                        avatar.setAttribute('role', 'img');
                        avatar.setAttribute('aria-label', `Sin foto de ${cliente.nombre}`);
                        avatar.textContent = iniciales;
                    }

                    cuerpo.appendChild(avatar);
                }

                const contenido = document.createElement('div');
                contenido.className = 'min-w-0 flex-1';
                const t = document.createElement('p');
                t.className = 'break-words font-semibold';
                t.textContent = titulo;
                contenido.appendChild(t);
                if (detalle) {
                    const d = document.createElement('p');
                    d.className = 'mt-1 text-sm opacity-80';
                    d.textContent = detalle;
                    contenido.appendChild(d);
                }
                if (cliente !== null) {
                    const destinoRenovacion = new URL(contenedor.dataset.clientesUrl, window.location.origin);
                    destinoRenovacion.searchParams.set('abrir_renovacion', cliente.id);

                    const acciones = document.createElement('div');
                    acciones.className = 'mt-3 flex justify-end';
                    const renovar = document.createElement('a');
                    renovar.href = destinoRenovacion.toString();
                    renovar.className = 'rounded-lg bg-teal-300 px-4 py-2 text-sm font-bold text-slate-950 transition hover:bg-teal-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-200';
                    renovar.textContent = 'Renovar';
                    acciones.appendChild(renovar);
                    contenido.appendChild(acciones);
                }
                cuerpo.appendChild(contenido);
                toast.appendChild(cuerpo);

                const descripcionTemporizador = document.createElement('span');
                descripcionTemporizador.className = 'sr-only';
                descripcionTemporizador.textContent = `Esta notificación desaparece en ${duracionNotificacion / 1000} segundos. Pasa el puntero o enfoca su acción para reiniciar el temporizador.`;
                toast.appendChild(descripcionTemporizador);

                const pistaProgreso = document.createElement('div');
                pistaProgreso.className = 'absolute inset-x-0 bottom-0 h-1 bg-white/10';
                pistaProgreso.setAttribute('aria-hidden', 'true');
                const barra = document.createElement('div');
                barra.className = 'h-full w-full origin-left bg-current motion-reduce:transition-none';
                barra.style.transform = 'scaleX(1)';
                pistaProgreso.appendChild(barra);
                toast.appendChild(pistaProgreso);

                contenedor.appendChild(toast);
                contenedor.scrollTop = contenedor.scrollHeight;
                cerrar.addEventListener('click', iniciarTemporizador(toast, barra));
            }

            let ocupado = false;

            async function sondear() {
                if (ocupado) return;
                ocupado = true;
                try {
                    const url = '/api/v1/biometrics/scan' + (ultimo === null ? '' : `?after=${ultimo}`);
                    const respuesta = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const datos = await respuesta.json().catch(() => ({}));
                    if (!respuesta.ok) throw new Error(datos.message || 'Lector no disponible.');
                    caido = false;
                    ultimo = datos.seq;
                    if (!datos.detected) return;
                    if (!datos.matched) {
                        mostrar('Huella no registrada', 'No coincide con ningún cliente.', 'error');
                    } else if (datos.cliente.vigente) {
                        mostrar(datos.cliente.nombre, `${datos.cliente.plan ?? 'Plan activo'} · vence ${datos.cliente.vence}`, 'ok', datos.cliente);
                    } else {
                        mostrar(datos.cliente.nombre, 'Membresía vencida o sin plan activo.', 'aviso', datos.cliente);
                    }
                } catch (error) {
                    if (!caido) mostrar('Lector sin conexión', error.message, 'error');
                    caido = true;
                } finally {
                    ocupado = false;
                }
            }

            setInterval(sondear, 1000);
            sondear();
        })();
    </script>
    @stack('scripts')
</body>
</html>
