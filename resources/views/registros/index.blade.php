@extends('layouts.app')

@section('title', 'Auditoría | Body Evolution Gym')

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal-300">Administración</p>
            <h1 class="mt-2 text-3xl font-black text-white">Auditoría del sistema</h1>
            <p class="mt-2 text-sm text-slate-400">Revisa quién realizó cada operación y cuándo ocurrió.</p>
        </div>

        <form method="GET" action="{{ route('registros.index') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-white/10 bg-slate-900/70 p-4">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">Acción</span>
                <input name="accion" value="{{ $filtros['accion'] ?? '' }}" maxlength="100" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm text-white" placeholder="egreso.creado">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">Desde</span>
                <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm text-white">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">Hasta</span>
                <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm text-white">
            </label>
            <button class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/15">Filtrar</button>
        </form>

        <section class="overflow-hidden rounded-3xl border border-white/10 bg-slate-900/70 shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Fecha</th>
                            <th class="px-5 py-3">Empleado</th>
                            <th class="px-5 py-3">Acción</th>
                            <th class="px-5 py-3">Registro</th>
                            <th class="px-5 py-3">Detalles</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($registros as $registro)
                            <tr class="align-top">
                                <td class="whitespace-nowrap px-5 py-3 text-slate-300">{{ $registro->created_at->format('d/m/Y H:i:s') }}</td>
                                <td class="px-5 py-3">
                                    <span class="block text-white">{{ $registro->usuario?->name ?? 'Cuenta eliminada' }}</span>
                                    <span class="text-xs text-slate-400">{{ $registro->usuario?->username ?? '—' }}</span>
                                </td>
                                <td class="px-5 py-3 font-medium text-teal-200">{{ $registro->accion }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ $registro->tipo ? class_basename($registro->tipo) : '—' }} {{ $registro->id_registro }}</td>
                                <td class="max-w-sm px-5 py-3 text-xs text-slate-400">
                                    @forelse ($registro->metadatos ?? [] as $clave => $valor)
                                        <span class="mr-2 inline-block"><strong class="text-slate-300">{{ $clave }}:</strong> {{ is_scalar($valor) ? $valor : json_encode($valor) }}</span>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">No hay registros para mostrar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-white/10 px-5 py-4 sm:px-7">{{ $registros->links() }}</div>
        </section>
    </div>
@endsection
