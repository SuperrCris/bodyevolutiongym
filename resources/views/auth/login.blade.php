<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso | Body Evolution Gym</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <main class="grid min-h-screen ">

        <section class="flex items-center justify-center px-6 py-12">
            <div class="w-full max-w-md">
                <div class="mb-10 justify-center align-middle text-center">
                    <div class="flex justify-center">
                    <img src="{{ asset('images/logo.png') }}" alt="Body Evolution Gym" class="mb-6 h-14 w-auto">
                  </div>
                    <h1 class="mt-3 text-center text-4xl font-black tracking-tight">Inicia sesión</h1>
                </div>
                @if ($errors->any())
                    <div class="mb-5 rounded-2xl border border-rose-300/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-100" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif
                <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                    @csrf
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-slate-300">Nombre o usuario</span>
                        <input type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3.5 outline-none transition placeholder:text-slate-600 focus:border-teal-300/70 focus:ring-4 focus:ring-teal-300/10" placeholder="Nombre completo o usuario">
                    </label>
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-slate-300">Contraseña</span>
                        <input type="password" name="password" required autocomplete="current-password" class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3.5 outline-none transition placeholder:text-slate-600 focus:border-teal-300/70 focus:ring-4 focus:ring-teal-300/10" placeholder="••••••••">
                    </label>
                    <label class="flex items-center gap-3 text-sm text-slate-400">
                        <input type="checkbox" name="remember" class="size-4 rounded border-white/20 bg-white/5 text-teal-400 focus:ring-teal-300">
                        Mantener la sesión iniciada
                    </label>
                    <button class="w-full rounded-2xl bg-teal-300 px-4 py-3.5 font-bold text-slate-950 transition hover:bg-teal-200 focus:outline-none focus:ring-4 focus:ring-teal-300/20">Entrar</button>
                </form>
                <div class="mt-6 mb-6 flex items-center gap-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <span class="h-px flex-1 bg-white/10"></span>
                    <span>O usa tu huella</span>
                    <span class="h-px flex-1 bg-white/10"></span>
                </div>
                <form method="POST" action="{{ route('login.fingerprint') }}" data-fingerprint-login>
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-center gap-3 rounded-2xl border border-teal-300/30 bg-teal-300/10 px-4 py-3.5 font-bold text-teal-100 transition hover:bg-teal-300/20 focus:outline-none focus:ring-4 focus:ring-teal-300/20 disabled:cursor-wait disabled:opacity-60">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-5 h-10">
                            <path d="M12 11.5v2.75m-4.25-2.5a4.25 4.25 0 0 1 8.5 0v2.5m-11.5-2.5a7.25 7.25 0 0 1 14.5 0v1.5m-10.5 2.5c.3 2.2 1.15 4.15 2.5 5.75m3.75-5.75c-.1 1.7-.5 3.25-1.25 4.75M5.2 17.5c.35 1.3.85 2.45 1.55 3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                        <span data-fingerprint-label>Entrar con huella</span>
                    </button>
                    <p class="mt-3 text-center text-xs text-slate-400">Presiona el botón y coloca el dedo en el lector.</p>
                </form>
            </div>
        </section>
    </main>
    @push('scripts')
        <script>
            document.querySelector('[data-fingerprint-login]')?.addEventListener('submit', (event) => {
                const button = event.currentTarget.querySelector('button[type="submit"]');
                button.disabled = true;
                button.querySelector('[data-fingerprint-label]').textContent = 'Esperando lector…';
            });
        </script>
    @endpush
</body>
</html>
