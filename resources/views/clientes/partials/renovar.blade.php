<div id="renovar-{{ $cliente->id }}" data-modal class="modal-backdrop z-50 hidden bg-slate-950/80">
    <div class="modal-panel max-h-[calc(100vh-3rem)] w-full max-w-lg overflow-y-auto">
        <div class="flex items-center justify-between"><h2 class="text-xl font-bold">Renovar membresía</h2><button type="button" data-close-modal class="text-2xl text-slate-500 hover:text-white">×</button></div>
        @if ($cliente->institucion_documento || $cliente->vigencia_documento || $cliente->detalles_documento)
            <div class="mt-4 rounded-xl border border-white/10 bg-white/[0.03] p-3">
                <p class="mb-2 text-sm font-semibold text-slate-300">Datos de promoción institucional</p>
                <dl class="space-y-2 text-sm">
                    @if ($cliente->institucion_documento)
                        <div>
                            <dt class="text-xs text-slate-400">Institución</dt>
                            <dd class="font-medium text-slate-200">{{ $cliente->institucion_documento }}</dd>
                        </div>
                    @endif
                    @if ($cliente->vigencia_documento)
                        <div>
                            <dt class="text-xs text-slate-400">Vigencia</dt>
                            <dd class="font-medium text-slate-200">{{ $cliente->vigencia_documento->format('d/m/Y') }}</dd>
                        </div>
                    @endif
                    @if ($cliente->detalles_documento)
                        <div>
                            <dt class="text-xs text-slate-400">Detalles</dt>
                            <dd class="whitespace-pre-line font-medium text-slate-200">{{ $cliente->detalles_documento }}</dd>
                        </div>
                    @endif
                </dl>
                @if ($cliente->vigencia_documento?->isBefore(today()))
                    <p role="alert" class="mt-3 rounded-lg border border-amber-300/30 bg-amber-300/10 px-3 py-2 text-sm font-semibold text-amber-200">La vigencia de esta promoción ya venció. Verifica los datos antes de renovar.</p>
                @endif
            </div>
        @endif
        <form method="POST" action="{{ route('clientes.renovar', $cliente) }}" class="mt-6 space-y-4">
            @csrf
            <label class="block">
                <span class="mb-1.5 block text-sm font-semibold text-slate-300">Plan</span><select name="tipo_membresia_id" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3.5 py-3 text-sm text-white outline-none focus:border-teal-300/60">
                    <option value="" class="bg-slate-900 text-slate-100">Selecciona un plan</option>
                    @foreach ($planes as $plan)
                    <option value="{{ $plan->id }}" class="bg-slate-900 text-slate-100">{{ $plan->nombre }} · {{ $plan->duracion_dias }} {{ $plan->duracion_dias > 1 ? 'días' : 'día' }} · ${{ number_format($plan->precio, 2) }}</option>
                    @endforeach
                </select>
            </label>
            <div class="grid gap-4 sm:grid-cols-2"><label class="block"><span class="mb-1.5 block text-sm font-semibold text-slate-300">Método de pago</span><select name="metodo_pago" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3.5 py-3 text-sm text-white outline-none focus:border-teal-300/60"><option value="efectivo" class="bg-slate-900 text-slate-100">Efectivo</option><option value="tarjeta" class="bg-slate-900 text-slate-100">Tarjeta</option><option value="transferencia" class="bg-slate-900 text-slate-100">Transferencia</option></select></label><label class="block"><span class="mb-1.5 block text-sm font-semibold text-slate-300">Referencia</span><input name="referencia" class="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-sm outline-none focus:border-teal-300/60"></label></div>
            <fieldset class="space-y-2">
                <legend class="text-sm font-semibold text-slate-300">Impresión del recibo</legend>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5">
                    <input type="radio" name="imprimir" value="dos" checked class="mt-0.5 h-4 w-4 border-white/20 bg-slate-950 text-teal-300 focus:ring-teal-300/60">
                    <span>
                        <span class="block text-sm font-semibold text-slate-200">Imprimir tickets</span>
                        <span class="block text-xs text-slate-400">Imprime dos copias.</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5">
                    <input type="radio" name="imprimir" value="uno" class="mt-0.5 h-4 w-4 border-white/20 bg-slate-950 text-teal-300 focus:ring-teal-300/60">
                    <span class="text-sm font-semibold text-slate-200">Imprimir un solo ticket</span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5">
                    <input type="radio" name="imprimir" value="no" class="mt-0.5 h-4 w-4 border-white/20 bg-slate-950 text-teal-300 focus:ring-teal-300/60">
                    <span class="text-sm font-semibold text-slate-200">No imprimir ticket</span>
                </label>
            </fieldset>
            <button class="button-primary w-full px-4 py-3">Confirmar renovación</button>

        </form>
    </div>
</div>
