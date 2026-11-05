@extends('layouts.app')

@section('title', 'Ajustes | Body Evolution Gym')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="mt-2 text-3xl font-black text-white">Ajustes</h1>

        </div>

        <section class="panel p-5 shadow-xl sm:p-7">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-teal-300">Preferencias</p>
                    <h2 class="mt-2 text-xl font-bold text-white">Duración de las notificaciones</h2>
                </div>
            </div>

            <form method="POST" action="{{ route('ajustes.notificaciones.update') }}" class="mt-5 flex flex-wrap items-end gap-4">
                @csrf
                @method('PUT')

                <label class="block w-full max-w-xs">
                    <span class="field-label">Tiempo visible (segundos)</span>
                    <input
                        type="number"
                        name="duracion_notificacion_segundos"
                        value="{{ old('duracion_notificacion_segundos', $duracionNotificacion) }}"
                        min="5"
                        max="120"
                        step="1"
                        required
                        aria-describedby="duracion-notificacion-ayuda"
                        class="field-control"
                    >
                    <span id="duracion-notificacion-ayuda" class="mt-2 block text-xs text-slate-500">
                        Puedes elegir entre 5 y 120 segundos.
                    </span>
                </label>

                <button type="submit" class="button-primary min-h-11 px-5 py-3 transition">
                    Guardar
                </button>
            </form>
        </section>

        @if ($esAdministrador)
            <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.3fr)_minmax(18rem,0.7fr)]">
                <section class="panel p-5 shadow-xl sm:p-7">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-teal-300">Tickets</p>
                            <h2 class="mt-2 text-xl font-bold text-white">Encabezado y pie del ticket</h2>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('ajustes.ticket.update') }}" class="mt-6 space-y-5">
                        @csrf
                        @method('PUT')

                        <label class="block">
                            <span class="field-label">Encabezado</span>
                            <textarea
                                name="encabezado"
                                rows="5"
                                maxlength="500"
                                aria-describedby="encabezado-ayuda"
                                class="field-control min-h-32 resize-y"
                            >{{ old('encabezado', $configuracionTicket['encabezado']) }}</textarea>
                        </label>

                        <label class="block">
                            <span class="field-label">Pie de página</span>
                            <textarea
                                name="pie"
                                rows="3"
                                maxlength="500"
                                aria-describedby="pie-ayuda"
                                class="field-control min-h-24 resize-y"
                            >{{ old('pie', $configuracionTicket['pie']) }}</textarea>
                        </label>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-5">
                            <p class="text-xs text-slate-500">Los cambios se aplican a los próximos tickets.</p>
                            <button type="submit" class="button-primary px-5 py-3 transition">
                                Guardar ajustes
                            </button>
                        </div>
                    </form>
                </section>


            </div>
        @endif
    </div>
@endsection
