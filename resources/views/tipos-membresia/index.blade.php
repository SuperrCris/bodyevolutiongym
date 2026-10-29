@extends('layouts.app')

@section('title', 'Membresías | Body Evolution Gym')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal-300">Administración</p>
                <h1 class="mt-2 text-3xl font-black text-white">Configurar membresías</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-400">
                    Define los planes disponibles, su duración en días y el precio para las próximas renovaciones.
                </p>
            </div>
            <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-slate-300">
                {{ $tiposMembresia->count() }} planes configurados
            </span>
        </div>

        <section class="panel p-5 shadow-xl sm:p-7">
            <h2 class="text-lg font-bold text-white">Crear membresía</h2>
            <form method="POST" action="{{ route('tipos-membresia.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @csrf
                <label class="block">
                    <span class="field-label">Nombre del plan</span>
                    <input
                        name="nombre"
                        value="{{ old('nombre') }}"
                        required
                        maxlength="100"
                        class="field-control"
                        placeholder="Ej. Mensual"
                    >
                </label>
                <label class="block">
                    <span class="field-label">Duración en días</span>
                    <input
                        type="number"
                        name="duracion_dias"
                        value="{{ old('duracion_dias') }}"
                        required
                        min="1"
                        max="3650"
                        step="1"
                        class="field-control"
                        placeholder="30"
                    >
                </label>
                <label class="block">
                    <span class="field-label">Precio</span>
                    <input
                        type="number"
                        name="precio"
                        value="{{ old('precio') }}"
                        required
                        min="0"
                        max="99999999.99"
                        step="0.01"
                        class="field-control"
                        placeholder="499.00"
                    >
                </label>
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <label class="flex items-center gap-3 pb-3 text-sm text-slate-300">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" name="activo" value="1" @checked(old('activo', true)) class="size-4 accent-teal-300">
                        Disponible
                    </label>
                    <button class="button-primary px-5 py-3 transition">
                        Crear plan
                    </button>
                </div>
            </form>
            <p class="mt-4 text-xs leading-5 text-slate-500">
                Los planes desactivados no aparecerán para nuevas renovaciones. Los registros de membresías y pagos existentes se conservan.
            </p>
        </section>

        <section class="panel overflow-hidden shadow-xl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-4 sm:px-7">
                <h2 class="text-lg font-bold text-white">Membresias</h2>
                <span class="text-sm text-slate-400">{{ $tiposMembresia->where('activo', true)->count() }} disponibles</span>
            </div>
            <div class="divide-y divide-white/5">
                @forelse ($tiposMembresia as $tipoMembresia)
                    <details class="group px-5 py-4 sm:px-7">
                        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3">
                            <span>
                                <span class="block font-semibold text-white">{{ $tipoMembresia->nombre }}</span>
                                <span class="text-sm text-slate-400">
                                    {{ $tipoMembresia->duracion_dias }} {{ $tipoMembresia->duracion_dias > 1 ? 'días' : 'dia' }} · ${{ number_format((float) $tipoMembresia->precio, 2) }}
                                </span>
                            </span>
                            <span class="flex items-center gap-3">
                                <span @class([
                                    'rounded-full border px-3 py-1 text-xs font-semibold',
                                    'border-teal-300/20 bg-teal-300/10 text-teal-200' => $tipoMembresia->activo,
                                    'border-amber-300/20 bg-amber-300/10 text-amber-200' => ! $tipoMembresia->activo,
                                ])>
                                    {{ $tipoMembresia->activo ? 'Disponible' : 'Pausada' }}
                                </span>
                                <span class="text-sm text-slate-400 transition group-open:rotate-180">⌄</span>
                            </span>
                        </summary>

                        <form
                            method="POST"
                            action="{{ route('tipos-membresia.update', $tipoMembresia) }}"
                            class="mt-5 grid gap-4 rounded-2xl border border-white/10 bg-slate-950/50 p-4 sm:grid-cols-2 xl:grid-cols-4"
                        >
                            @csrf
                            @method('PUT')
                            <label class="block">
                                <span class="field-label">Nombre del plan</span>
                                <input
                                    name="nombre"
                                    value="{{ $tipoMembresia->nombre }}"
                                    required
                                    maxlength="100"
                                    class="field-control-solid"
                                >
                            </label>
                            <label class="block">
                                <span class="field-label">Duración en días</span>
                                <input
                                    type="number"
                                    name="duracion_dias"
                                    value="{{ $tipoMembresia->duracion_dias }}"
                                    required
                                    min="1"
                                    max="3650"
                                    step="1"
                                    class="field-control-solid"
                                >
                            </label>
                            <label class="block">
                                <span class="field-label">Precio</span>
                                <input
                                    type="number"
                                    name="precio"
                                    value="{{ $tipoMembresia->precio }}"
                                    required
                                    min="0"
                                    max="99999999.99"
                                    step="0.01"
                                    class="field-control-solid"
                                >
                            </label>
                            <div class="flex flex-wrap items-end justify-between gap-4">
                                <label class="flex items-center gap-3 pb-3 text-sm text-slate-300">
                                    <input type="hidden" name="activo" value="0">
                                    <input
                                        type="checkbox"
                                        name="activo"
                                        value="1"
                                        @checked($tipoMembresia->activo)
                                        class="size-4 accent-teal-300"
                                    >
                                    Disponible
                                </label>
                                <button class="rounded-xl border border-teal-300/30 px-5 py-3 font-semibold text-teal-200 transition hover:bg-teal-300/10">
                                    Guardar cambios
                                </button>
                            </div>
                        </form>
                    </details>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-400">Todavía no hay planes configurados.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
