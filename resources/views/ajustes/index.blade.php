@extends('layouts.app')

@section('title', 'Ajustes | Body Evolution Gym')

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal-300">Configuración del sistema</p>
            <h1 class="mt-2 text-3xl font-black text-white">Ajustes</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-400">
                Personaliza las notificaciones de tu cuenta y, si eres administrador, los tickets del gimnasio.
            </p>
        </div>

        <section class="panel p-5 shadow-xl sm:p-7">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-teal-300">Preferencia personal</p>
                    <h2 class="mt-2 text-xl font-bold text-white">Duración de las notificaciones</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-400">
                        Elige cuántos segundos permanecerán visibles los avisos de lectura de huella. Al pasar el puntero encima, el contador se pausa y reinicia al salir.
                    </p>
                </div>
                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-slate-300">
                    Tu cuenta
                </span>
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
                    Guardar preferencia
                </button>
            </form>
        </section>

        @if ($esAdministrador)
            <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.3fr)_minmax(18rem,0.7fr)]">
                <section class="panel p-5 shadow-xl sm:p-7">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-teal-300">Solo administradores</p>
                            <h2 class="mt-2 text-xl font-bold text-white">Encabezado y pie del ticket</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-400">
                                Este texto se imprimirá en los recibos y aparecerá también en la vista previa del pago.
                            </p>
                        </div>
                        <span class="rounded-full border border-teal-300/20 bg-teal-300/10 px-3 py-1 text-xs font-semibold text-teal-100">
                            Tickets
                        </span>
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
                            <span id="encabezado-ayuda" class="mt-2 block text-xs leading-5 text-slate-500">
                                Una línea por dato, por ejemplo nombre del gimnasio, RFC y teléfono. Hasta 500 caracteres.
                            </span>
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
                            <span id="pie-ayuda" class="mt-2 block text-xs leading-5 text-slate-500">
                                Mensaje de agradecimiento, redes o información adicional. Déjalo vacío para no imprimirlo.
                            </span>
                        </label>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-5">
                            <p class="text-xs text-slate-500">Los cambios se aplican a los próximos tickets.</p>
                            <button type="submit" class="button-primary px-5 py-3 transition">
                                Guardar ajustes
                            </button>
                        </div>
                    </form>
                </section>

                <aside class="panel p-5 shadow-xl sm:p-7" aria-label="Vista previa del ticket">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-400">Vista previa</p>
                    <div class="mt-4 rounded-xl bg-white p-5 font-mono text-xs leading-5 text-slate-900 shadow-inner">
                        <p class="whitespace-pre-line text-center font-bold">{{ old('encabezado', $configuracionTicket['encabezado']) }}</p>
                        <div class="my-3 border-t border-dashed border-slate-400"></div>
                        <p class="text-center font-bold">RECIBO DE PAGO</p>
                        <p class="mt-3">Folio <span class="float-right">#0000</span></p>
                        <p>Cliente <span class="float-right">Nombre</span></p>
                        <p class="mt-3 font-bold">Total <span class="float-right">$0.00</span></p>
                        @if (filled(old('pie', $configuracionTicket['pie'])))
                            <div class="my-3 border-t border-dashed border-slate-400"></div>
                            <p class="whitespace-pre-line text-center">{{ old('pie', $configuracionTicket['pie']) }}</p>
                        @endif
                    </div>
                    <p class="mt-4 text-xs leading-5 text-slate-500">
                        La primera línea del encabezado se destaca en la impresora térmica.
                    </p>
                </aside>
            </div>
        @endif
    </div>
@endsection
