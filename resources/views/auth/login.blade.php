<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso | Body Evolution Gym</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <main class="grid min-h-screen lg:grid-cols-[1.1fr_0.9fr]">
        <section class="hidden flex-col justify-between bg-teal-400 p-10 text-slate-950 lg:flex">
            <div class="text-sm font-black tracking-[0.3em]">BODY EVOLUTION</div>
            <div>
                <p class="mb-4 text-sm font-semibold uppercase tracking-[0.25em] text-slate-800/70">Panel de operaciones</p>
                <h1 class="max-w-xl text-6xl font-black leading-[0.95] tracking-tight">Cada sesión cuenta.</h1>
                <p class="mt-6 max-w-md text-lg text-slate-800/80">Administra clientes, membresías y renovaciones desde un solo lugar.</p>
            </div>
            <p class="text-sm text-slate-800/70">Acceso protegido para el equipo del gimnasio.</p>
        </section>
        <section class="flex items-center justify-center px-6 py-12">
            <div class="w-full max-w-md">
                <div class="mb-10">
                    <span class="mb-5 grid size-12 place-items-center rounded-2xl bg-teal-400 font-black text-slate-950 lg:hidden">BE</span>
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-teal-300">Bienvenido de nuevo</p>
                    <h1 class="mt-3 text-4xl font-black tracking-tight">Inicia sesión</h1>
                    <p class="mt-3 text-slate-400">Accede al panel para continuar con la operación.</p>
                </div>
                <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                    @csrf
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-slate-300">Correo electrónico</span>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3.5 outline-none transition placeholder:text-slate-600 focus:border-teal-300/70 focus:ring-4 focus:ring-teal-300/10" placeholder="admin@bodyevolutiongym.test">
                    </label>
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-slate-300">Contraseña</span>
                        <input type="password" name="password" required class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3.5 outline-none transition placeholder:text-slate-600 focus:border-teal-300/70 focus:ring-4 focus:ring-teal-300/10" placeholder="••••••••">
                    </label>
                    <label class="flex items-center gap-3 text-sm text-slate-400">
                        <input type="checkbox" name="remember" class="size-4 rounded border-white/20 bg-white/5 text-teal-400 focus:ring-teal-300">
                        Mantener la sesión iniciada
                    </label>
                    <button class="w-full rounded-2xl bg-teal-300 px-4 py-3.5 font-bold text-slate-950 transition hover:bg-teal-200 focus:outline-none focus:ring-4 focus:ring-teal-300/20">Entrar al panel</button>
                </form>
                <p class="mt-8 text-center text-xs text-slate-500">Demo local: admin@bodyevolutiongym.test / password</p>
            </div>
        </section>
    </main>
</body>
</html>
