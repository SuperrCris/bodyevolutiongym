@extends('layouts.app')

@section('title', 'Reporte de membresías | Body Evolution Gym')

@section('content')
    <div class="space-y-7">
        <header class="relative overflow-hidden rounded-3xl border border-teal-300/15 bg-slate-900/80 p-6 shadow-2xl shadow-teal-950/20 sm:p-8">
            <div class="pointer-events-none absolute -right-12 -top-20 size-64 rounded-full bg-teal-300/10 blur-3xl"></div>
            <div class="relative flex flex-col gap-6 xl:justify-between">
                <div>
                  <!--  <span class="inline-flex items-center gap-2 rounded-full border border-teal-300/20 bg-teal-300/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-teal-200">
                        <span class="size-1.5 rounded-full bg-teal-300"></span>
                        Finanzas · Reporte por período
                    </span> -->
                    <h1 class="text-3xl font-black tracking-tight text-white sm:text-4xl">Reporte de membresías</h1>
                   <!--  <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-400">
                        Consulta las membresías cobradas dentro de un rango de fechas y horas.
                    </p>-->
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <form method="GET" action="{{ route('reportes.index') }}" class="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap sm:items-end">
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">Fecha inicial</span>
                            <input type="date" name="desde" value="{{ old('desde', $desde->toDateString()) }}" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3.5 py-3 text-sm text-white outline-none focus:border-teal-300/60">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">Hora inicial</span>
                            <input type="time" name="hora_desde" value="{{ old('hora_desde', $desde->format('H:i')) }}" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3.5 py-3 text-sm text-white outline-none focus:border-teal-300/60">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">Fecha final</span>
                            <input type="date" name="hasta" value="{{ old('hasta', $hasta->toDateString()) }}" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3.5 py-3 text-sm text-white outline-none focus:border-teal-300/60">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">Hora final</span>
                            <input type="time" name="hora_hasta" value="{{ old('hora_hasta', $hasta->format('H:i')) }}" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3.5 py-3 text-sm text-white outline-none focus:border-teal-300/60">
                        </label>
                        <label class="col-span-2 block sm:col-span-1">
                            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">ID de cliente</span>
                            <input type="number" name="cliente_id" value="{{ $clienteId ?? '' }}" min="1" step="1" inputmode="numeric" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3.5 py-3 text-sm text-white outline-none focus:border-teal-300/60" placeholder="Todos">
                        </label>
                        <button class="col-span-2 rounded-xl bg-white/10 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/15 sm:col-span-1">Generar reporte</button>
                    </form>
                    <a href="{{ route('reportes.excel', array_filter(['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'hora_desde' => $desde->format('H:i'), 'hora_hasta' => $hasta->format('H:i'), 'cliente_id' => $clienteId], fn ($value) => $value !== null)) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-teal-300/25 bg-teal-300/5 px-4 py-3 text-sm font-bold text-teal-100 transition hover:bg-teal-300/10">
                        Descargar Excel
                    </a>
                </div>
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-3">
            <article class="rounded-2xl border border-teal-300/20 bg-gradient-to-br from-teal-300/10 to-teal-300/[0.02] p-5">
                <p class="text-sm font-medium text-teal-100/70">Membresías</p>
                <p class="mt-2 text-3xl font-black tracking-tight text-teal-100">$<span data-memberships-grand-total>{{ number_format((float) $corte['membresias_total'], 2) }}</span></p>
                <p class="mt-1 text-xs text-teal-100/50">{{ $desde->format('d/m/Y H:i') }} — {{ $hasta->format('d/m/Y H:i') }}</p>
            </article>
            <article class=" rounded-2xl border border-amber-300/15 bg-amber-300/[0.04] p-5">
                <p class="text-sm font-medium text-amber-100/70">Conceptos netos:</p>
                <p class="mt-2 text-2xl font-black text-amber-100">$<span data-adjustments-total>{{ number_format((float) $corte['ajustes_total'], 2) }}</span></p>
            </article>
            <article class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
                <p class="text-sm font-medium text-slate-400">Total del corte</p>
                <p class="mt-2 text-2xl font-black text-white">$<span data-grand-total>{{ number_format((float) $corte['total'], 2) }}</span></p>
            </article>
        </section>

        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.8fr)_minmax(21rem,0.9fr)]">
            <section class="panel overflow-hidden shadow-xl">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-4 sm:px-7">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Pagos registrados</p>
                        <h2 class="mt-1 font-bold text-white">Detalle de membresías</h2>
                    </div>
                    <span class="rounded-full bg-teal-300/10 px-3 py-1 text-xs font-semibold text-teal-100">{{ $movimientos->total() }} pagos</span>
                </div>

                <div class="overflow-x-auto">
                    <table data-memberships-table class="w-full min-w-[700px] text-left text-sm">
                        <thead class="bg-white/[0.035] text-[11px] uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">ID</th>
                                <th class="px-4 py-3">Nombre</th>
                                <th class="px-4 py-3">Fecha y hora</th>
                                <th class="px-4 py-3">Tipo de pago</th>
                                <th class="px-4 py-3 text-right">Monto</th>
                                <th class="px-4 py-3">Tipo de membresía</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse ($movimientos as $movimiento)
                                <tr data-membership-row class="transition hover:bg-white/[0.025]">
                                    <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-300">{{ $movimiento->cliente_id ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-white">{{ $movimiento->cliente_id === null ? ($movimiento->nombre_comprador ?: 'Venta sin cliente') : trim($movimiento->cliente_nombre.' '.$movimiento->cliente_apellido) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-400">{{ \Illuminate\Support\Carbon::parse($movimiento->fecha)->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3">
                                        @php
                                            $nombreMetodo = match ($movimiento->metodo_pago) {
                                                'efectivo' => 'Efectivo',
                                                'tarjeta' => 'Tarjeta',
                                                'transferencia' => 'Transferencia',
                                                default => $movimiento->metodo_pago ?: '—',
                                            };
                                        @endphp
                                        <span class="inline-flex rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-xs font-semibold text-slate-200">{{ $nombreMetodo }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-bold text-teal-100">${{ number_format((float) $movimiento->monto, 2) }}</td>
                                    <td class="px-4 py-3 text-slate-300">{{ $movimiento->tipo_membresia ?: 'Membresía' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No hay pagos de membresía en este período.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-t border-teal-300/15 bg-teal-300/[0.04]">
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-right text-xs font-bold uppercase tracking-wide text-slate-400">Total de membresías</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right text-base font-black text-teal-100">${{ number_format((float) $corte['membresias_total'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="border-t border-white/10 px-5 py-4 sm:px-7">{{ $movimientos->links() }}</div>
            </section>

            @if ($puedeEditarAjustes)
                <form method="POST" action="{{ route('reportes.ajustes') }}" data-corte-form class="space-y-4">
                    @csrf
                    <input type="hidden" name="fecha" value="{{ $desde->toDateString() }}">
                    <input type="hidden" name="desde" value="{{ $desde->toDateString() }}">
                    <input type="hidden" name="hasta" value="{{ $hasta->toDateString() }}">
                    <input type="hidden" name="hora_desde" value="{{ $desde->format('H:i') }}">
                    <input type="hidden" name="hora_hasta" value="{{ $hasta->format('H:i') }}">
            @else
                <section class="space-y-4">
            @endif

                <div class="flex items-end justify-between gap-3 px-1">
                    <div>
                         <!--<p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">{{ $puedeEditarAjustes ? 'Captura manual' : 'Resumen del período' }}</p>-->
                        <h2 class="mt-1 text-lg font-bold text-white">Conceptos</h2>
                    </div>
                   <!-- <span class="text-right text-xs text-slate-500">{{ $desde->format('d/m/Y H:i') }}<br>— {{ $hasta->format('d/m/Y H:i') }}</span> -->
                </div>

                @foreach ($corte['metodos'] as $metodoPago => $datosMetodo)
                    @php
                        $lineasAjuste = $puedeEditarAjustes
                            ? old('ajustes.'.$metodoPago, $datosMetodo['ajustes'])
                            : $datosMetodo['ajustes'];
                        if (! is_array($lineasAjuste)) {
                            $lineasAjuste = $datosMetodo['ajustes'];
                        }
                        $lineasAjuste = array_values(array_filter($lineasAjuste, 'is_array'));
                    @endphp

                    <details
                        data-method-card
                        data-metodo="{{ $metodoPago }}"
                        data-membership-amount="{{ $datosMetodo['membresias'] }}"
                        data-next-index="{{ count($lineasAjuste) }}"
                        class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900/75 shadow-lg shadow-black/10"
                        {{ $metodoPago === 'efectivo' ? 'open' : '' }}
                    >
                        <summary class="group flex cursor-pointer list-none items-center justify-between gap-3 bg-white/[0.035] px-4 py-3 outline-none transition hover:bg-white/[0.06] focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-300 [&::-webkit-details-marker]:hidden">
                            <span class="font-bold text-white">{{ $datosMetodo['nombre'] }}</span>
                            <span class="flex items-center gap-3">
                                <span class="text-right">
                                    <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Total</span>
                                    <span class="text-sm font-bold text-teal-100">$ <span data-method-total>{{ number_format((float) $datosMetodo['total'], 2) }}</span></span>
                                </span>
                                <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="size-4 text-slate-400 transition-transform duration-200 group-open:rotate-180">
                                    <path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"></path>
                                </svg>
                            </span>
                        </summary>

                        <div class="space-y-3 border-t border-white/10 p-4">
                            <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-950/70 px-3 py-2.5">
                                <span class="text-sm font-medium text-slate-300">Membresías</span>
                                <span class="whitespace-nowrap text-sm font-bold text-white">$ {{ number_format((float) $datosMetodo['membresias'], 2) }}</span>
                            </div>

                            <div class="space-y-2.5" data-ajustes-list>
                                @foreach ($lineasAjuste as $indice => $linea)
                                    @if ($puedeEditarAjustes)
                                        <div data-adjustment-row class="grid grid-cols-[minmax(0,1fr)_minmax(6.5rem,0.8fr)_auto] items-end gap-2">
                                        <label class="min-w-0">
                                            <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Concepto</span>
                                            <input
                                                type="text"
                                                name="ajustes[{{ $metodoPago }}][{{ $indice }}][concepto]"
                                                value="{{ is_scalar($linea['concepto'] ?? null) ? $linea['concepto'] : '' }}"
                                                maxlength="80"
                                                required
                                                class="w-full min-w-0 rounded-lg border border-white/10 bg-slate-950 px-2.5 py-2 text-sm text-white outline-none transition focus:border-teal-300/60"
                                            >
                                        </label>
                                        <label class="min-w-0">
                                            <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Importe</span>
                                            <span class="flex items-center rounded-lg border border-white/10 bg-slate-950 px-2.5 text-xs text-slate-500 focus-within:border-teal-300/60">
                                                <span>$</span>
                                                <input
                                                    type="number"
                                                    name="ajustes[{{ $metodoPago }}][{{ $indice }}][monto]"
                                                    value="{{ is_scalar($linea['monto'] ?? null) ? $linea['monto'] : '' }}"
                                                    step="0.01"
                                                    data-adjustment-amount
                                                    aria-label="Importe de {{ is_scalar($linea['concepto'] ?? null) ? $linea['concepto'] : 'ajuste' }}"
                                                    class="w-full min-w-0 bg-transparent px-1.5 py-2 text-right text-sm text-white outline-none"
                                                >
                                            </span>
                                        </label>
                                        <button type="button" data-remove-adjustment aria-label="Eliminar renglón" class="mb-0.5 grid size-8 place-items-center rounded-lg border border-white/10 text-slate-500 transition hover:border-rose-300/30 hover:bg-rose-300/10 hover:text-rose-200">×</button>
                                        </div>
                                    @else
                                        <div class="flex items-center justify-between gap-3 rounded-lg border border-white/5 bg-slate-950/60 px-3 py-2.5">
                                            <span class="text-sm text-slate-300">{{ $linea['concepto'] }}</span>
                                            <span class="whitespace-nowrap text-sm font-semibold text-slate-100">${{ number_format((float) ($linea['monto'] === '' ? 0 : $linea['monto']), 2) }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            @if ($puedeEditarAjustes)
                                <template data-adjustment-template>
                                <div data-adjustment-row class="grid grid-cols-[minmax(0,1fr)_minmax(6.5rem,0.8fr)_auto] items-end gap-2">
                                    <label class="min-w-0">
                                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Concepto</span>
                                        <input type="text" data-field="concepto" maxlength="80" required class="w-full min-w-0 rounded-lg border border-white/10 bg-slate-950 px-2.5 py-2 text-sm text-white outline-none transition focus:border-teal-300/60">
                                    </label>
                                    <label class="min-w-0">
                                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Importe</span>
                                        <span class="flex items-center rounded-lg border border-white/10 bg-slate-950 px-2.5 text-xs text-slate-500 focus-within:border-teal-300/60">
                                            <span>$</span>
                                            <input type="number" data-field="monto" step="0.01" data-adjustment-amount class="w-full min-w-0 bg-transparent px-1.5 py-2 text-right text-sm text-white outline-none">
                                        </span>
                                    </label>
                                    <button type="button" data-remove-adjustment aria-label="Eliminar renglón" class="mb-0.5 grid size-8 place-items-center rounded-lg border border-white/10 text-slate-500 transition hover:border-rose-300/30 hover:bg-rose-300/10 hover:text-rose-200">×</button>
                                </div>
                                </template>

                                <button type="button" data-add-adjustment class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-teal-300/25 px-2.5 py-1.5 text-xs font-semibold text-teal-200 transition hover:border-teal-200/50 hover:bg-teal-300/5">
                                    <span class="text-base leading-none">+</span>
                                    Agregar concepto
                                </button>
                            @endif

                        </div>
                    </details>
                @endforeach

                <div class="rounded-2xl border border-teal-300/15 bg-teal-300/[0.04] p-4">
                    @if ($puedeEditarAjustes)
                        <p class="mb-3 text-xs leading-5 text-slate-400">Los ajustes se guardan por fecha y método, aunque el rango horario cubra solo parte del día. Un importe negativo se resta del total.</p>
                        <button class="button-primary w-full px-5 py-3 text-sm shadow-lg shadow-teal-950/20">Guardar ajustes del día</button>
                    @else
                        <p class="text-xs leading-5 text-slate-400">Los ajustes manuales se incluyen completos por cada fecha del período y se muestran agrupados por concepto. Para editarlos, selecciona un rango de un solo día.</p>
                    @endif
                </div>
            @if ($puedeEditarAjustes)
                </form>
            @else
                </section>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-corte-form]');
            if (!form) {
                return;
            }

            const formatoMoneda = new Intl.NumberFormat('es-MX', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            function recalcularTotales() {
                let totalMembresias = 0;
                let totalAjustes = 0;

                document.querySelectorAll('[data-method-card]').forEach((tarjeta) => {
                    const membresias = Number(tarjeta.dataset.membershipAmount) || 0;
                    let ajustes = 0;

                    tarjeta.querySelectorAll('[data-adjustment-amount]').forEach((campo) => {
                        ajustes += Number(campo.value) || 0;
                    });

                    const totalMetodo = membresias + ajustes;
                    totalMembresias += membresias;
                    totalAjustes += ajustes;
                    tarjeta.querySelector('[data-method-total]').textContent = formatoMoneda.format(totalMetodo);
                });

                document.querySelector('[data-memberships-grand-total]').textContent = formatoMoneda.format(totalMembresias);
                document.querySelector('[data-adjustments-total]').textContent = formatoMoneda.format(totalAjustes);
                document.querySelector('[data-grand-total]').textContent = formatoMoneda.format(totalMembresias + totalAjustes);
            }

            document.querySelectorAll('[data-add-adjustment]').forEach((boton) => {
                boton.addEventListener('click', () => {
                    const tarjeta = boton.closest('[data-method-card]');
                    const plantilla = tarjeta.querySelector('[data-adjustment-template]');
                    const indice = Number(tarjeta.dataset.nextIndex);
                    const fragmento = plantilla.content.cloneNode(true);

                    fragmento.querySelectorAll('[data-field]').forEach((campo) => {
                        campo.name = `ajustes[${tarjeta.dataset.metodo}][${indice}][${campo.dataset.field}]`;
                    });

                    tarjeta.dataset.nextIndex = String(indice + 1);
                    tarjeta.querySelector('[data-ajustes-list]').append(fragmento);
                    recalcularTotales();
                });
            });

            form.addEventListener('click', (evento) => {
                const botonEliminar = evento.target.closest('[data-remove-adjustment]');
                if (botonEliminar) {
                    botonEliminar.closest('[data-adjustment-row]').remove();
                    recalcularTotales();
                }
            });

            form.addEventListener('input', recalcularTotales);
            recalcularTotales();
        })();
    </script>
@endpush
