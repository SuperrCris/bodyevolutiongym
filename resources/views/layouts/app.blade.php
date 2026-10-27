<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Body Evolution Gym')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top_right,_rgba(20,184,166,0.14),_transparent_34%),linear-gradient(135deg,#020617_0%,#0f172a_52%,#111827_100%)]">
        <header class="border-b border-white/10 bg-slate-950/70 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-5">
                <a href="{{ route('clientes.index') }}" class="flex items-center gap-3">
                    <img src="{{ asset('images/logo.png') }}" alt="Body Evolution Gym" class="h-12 w-auto">
                </a>
                <div class="flex items-center gap-4">
                    <span class="hidden text-sm text-slate-300 sm:block">{{ auth()->user()->name }}</span>
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
    <div id="toastHuella" class="pointer-events-none fixed right-6 top-6 z-50 flex w-80 flex-col gap-3"></div>
    <script>
        (() => {
            const contenedor = document.getElementById('toastHuella');
            let ultimo = null;
            let caido = false;

            function mostrar(titulo, detalle, tono) {
                const estilos = {
                    ok: 'border-teal-300/30 bg-teal-950/95 text-teal-100',
                    aviso: 'border-amber-300/30 bg-amber-950/95 text-amber-100',
                    error: 'border-rose-300/30 bg-rose-950/95 text-rose-100',
                };
                const toast = document.createElement('div');
                toast.className = `pointer-events-auto rounded-2xl border px-4 py-3 shadow-2xl backdrop-blur transition ${estilos[tono]}`;
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
                contenedor.appendChild(toast);
                setTimeout(() => toast.remove(), 6000);
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
                        mostrar(datos.cliente.nombre, `${datos.cliente.plan ?? 'Plan activo'} · vence ${datos.cliente.vence}`, 'ok');
                    } else {
                        mostrar(datos.cliente.nombre, 'Membresía vencida o sin plan activo.', 'aviso');
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
