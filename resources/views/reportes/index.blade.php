@extends('layouts.app')

@section('title', 'Reportes financieros | Body Evolution Gym')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal-300">Finanzas</p>
                <h1 class="mt-2 text-3xl font-black text-white">Reporte de movimientos</h1>
                <p class="mt-2 text-sm text-slate-400">Consulta ingresos por membresías y egresos por categoría.</p>
            </div>
            <a href="{{ route('reportes.csv', request()->query()) }}" class="rounded-xl border border-teal-300/30 px-4 py-2.5 text-sm font-semibold text-teal-200 transition hover:bg-teal-300/10">Descargar CSV</a>
        </div>

        <form method="GET" action="{{ route('reportes.index') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-white/10 bg-slate-900/70 p-4">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">Desde</span>
                <input type="date" name="desde" value="{{ $desde->toDateString() }}" required class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm text-white">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">Hasta</span>
                <input type="date" name="hasta" value="{{ $hasta->toDateString() }}" required class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm text-white">
            </label>
            <button class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/15">Aplicar fechas</button>
        </form>

        <section class="grid gap-4 sm:grid-cols-3">
            <article class="rounded-2xl border border-teal-300/20 bg-teal-300/5 p-5">
                <p class="text-sm text-teal-100/70">Ingresos</p>
                <p class="mt-2 text-2xl font-black text-teal-100">${{ number_format((float) $resumen['ingresos'], 2) }}</p>
            </article>
            <article class="rounded-2xl border border-rose-300/20 bg-rose-300/5 p-5">
                <p class="text-sm text-rose-100/70">Egresos</p>
                <p class="mt-2 text-2xl font-black text-rose-100">${{ number_format((float) $resumen['egresos'], 2) }}</p>
            </article>
            <article class="rounded-2xl border border-white/10 bg-white/5 p-5">
                <p class="text-sm text-slate-400">Balance</p>
                <p class="mt-2 text-2xl font-black text-white">${{ number_format((float) $resumen['balance'], 2) }}</p>
            </article>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <article class="rounded-2xl border border-white/10 bg-slate-900/70 p-5">
                <h2 class="font-bold text-white">Tipos de ingreso</h2>
                <div class="mt-4 divide-y divide-white/5">
                    @foreach ($resumen['ingresos_por_tipo'] as $ingreso)
                        <div class="flex items-center justify-between gap-4 py-3 text-sm">
                            <span class="text-slate-300">{{ $ingreso->categoria }}</span>
                            <span class="font-semibold text-teal-100">${{ number_format((float) $ingreso->total, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </article>
            <article class="rounded-2xl border border-white/10 bg-slate-900/70 p-5">
                <h2 class="font-bold text-white">Tipos de egreso</h2>
                <div class="mt-4 divide-y divide-white/5">
                    @forelse ($resumen['egresos_por_categoria'] as $egreso)
                        <div class="flex items-center justify-between gap-4 py-3 text-sm">
                            <span class="text-slate-300">{{ $egreso->categoria }}</span>
                            <span class="font-semibold text-rose-100">${{ number_format((float) $egreso->total, 2) }}</span>
                        </div>
                    @empty
                        <p class="py-3 text-sm text-slate-400">No hay egresos en este rango.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="overflow-hidden rounded-3xl border border-white/10 bg-slate-900/70 shadow-xl">
            <div class="border-b border-white/10 px-5 py-4 sm:px-7">
                <h2 class="font-bold text-white">Detalle de movimientos</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Fecha</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Categoría / concepto</th>
                            <th class="px-5 py-3">Método</th>
                            <th class="px-5 py-3 text-right">Ingreso</th>
                            <th class="px-5 py-3 text-right">Egreso</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($movimientos as $movimiento)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-300">{{ \Illuminate\Support\Carbon::parse($movimiento->fecha)->format('d/m/Y') }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $movimiento->tipo_movimiento === 'ingreso' ? 'bg-teal-300/10 text-teal-200' : 'bg-rose-300/10 text-rose-200' }}">
                                        {{ $movimiento->tipo_movimiento === 'ingreso' ? 'Ingreso' : 'Egreso' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="block font-medium text-white">{{ $movimiento->categoria }}</span>
                                    <span class="text-xs text-slate-400">{{ $movimiento->concepto }}</span>
                                </td>
                                <td class="px-5 py-3 text-slate-300">{{ $movimiento->metodo_pago ?: '—' }}</td>
                                <td class="px-5 py-3 text-right text-teal-100">${{ number_format((float) $movimiento->ingreso, 2) }}</td>
                                <td class="px-5 py-3 text-right text-rose-100">${{ number_format((float) $movimiento->egreso, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">No hay movimientos en este rango.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-white/10 px-5 py-4 sm:px-7">{{ $movimientos->links() }}</div>
        </section>
    </div>
@endsection
