<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Body Evolution Gym')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
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
        aria-live="polite"
        aria-relevant="additions"
        class="fixed bottom-4 left-4 z-50 flex max-h-[calc(100vh-2rem)] w-[calc(100vw-2rem)] flex-col justify-end gap-3 overflow-y-auto sm:bottom-6 sm:left-6 sm:w-96"
    ></div>
    <script>
        (() => {
            const contenedor = document.getElementById('toastHuella');
            let ultimo = null;
            let caido = false;

            function mostrar(titulo, detalle, tono, clienteId = null) {
                const estilos = {
                    ok: 'border-teal-300/30 bg-teal-950/95 text-teal-100',
                    aviso: 'border-amber-300/30 bg-amber-950/95 text-amber-100',
                    error: 'border-rose-300/30 bg-rose-950/95 text-rose-100',
                };
                const toast = document.createElement('div');
                toast.className = `animate-[toast-rise_180ms_ease-out] motion-reduce:animate-none rounded-2xl border px-4 py-3 shadow-2xl backdrop-blur transition ${estilos[tono]}`;
                const t = document.createElement('p');
                t.className = 'font-semibold';
                t.textContent = titulo;
                toast.appendChild(t);
                if (detalle) {
                    const d = document.createElement('p');
                    d.className = 'mt-1 text-sm opacity-80';
                    d.textContent = detalle;
                    toast.appendChild(d);
                }
                if (clienteId !== null) {
                    const destinoRenovacion = new URL(contenedor.dataset.clientesUrl, window.location.origin);
                    destinoRenovacion.searchParams.set('abrir_renovacion', clienteId);

                    const acciones = document.createElement('div');
                    acciones.className = 'mt-3 flex justify-end';
                    const renovar = document.createElement('a');
                    renovar.href = destinoRenovacion.toString();
                    renovar.className = 'rounded-lg bg-teal-300 px-4 py-2 text-sm font-bold text-slate-950 transition hover:bg-teal-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-200';
                    renovar.textContent = 'Renovar';
                    acciones.appendChild(renovar);
                    toast.appendChild(acciones);
                }
                contenedor.appendChild(toast);
                contenedor.scrollTop = contenedor.scrollHeight;
                setTimeout(() => toast.remove(), 20000);
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
                        mostrar(datos.cliente.nombre, `${datos.cliente.plan ?? 'Plan activo'} · vence ${datos.cliente.vence}`, 'ok', datos.cliente.id);
                    } else {
                        mostrar(datos.cliente.nombre, 'Membresía vencida o sin plan activo.', 'aviso', datos.cliente.id);
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
