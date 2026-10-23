<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Body Evolution Gym')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top_right,_rgba(20,184,166,0.14),_transparent_34%),linear-gradient(135deg,#020617_0%,#0f172a_52%,#111827_100%)]">
        <header class="border-b border-white/10 bg-slate-950/70 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-5">
                <a href="{{ route('clientes.index') }}" class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-2xl bg-teal-400 font-black text-slate-950">BE</span>
                    <span>
                        <span class="block text-sm font-semibold tracking-[0.2em] text-teal-300">BODY EVOLUTION</span>
                        <span class="block text-xs text-slate-400">Gestión inteligente del gimnasio</span>
                    </span>
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
    @stack('scripts')
</body>
</html>
