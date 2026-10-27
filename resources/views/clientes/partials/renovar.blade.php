<div id="renovar-{{ $cliente->id }}" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm">
    <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl">
        <div class="flex items-center justify-between"><h2 class="text-xl font-bold">Renovar membresía</h2><button type="button" data-close-modal class="text-2xl text-slate-500 hover:text-white">×</button></div>
        <form method="POST" action="{{ route('clientes.renew', $cliente) }}" class="mt-6 space-y-4">
            @csrf
            <label class="block"><span class="mb-1.5 block text-sm font-semibold text-slate-300">Plan</span><select name="tipo_membresia_id" required class="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-sm outline-none focus:border-teal-300/60"><option value="">Selecciona un plan</option>@foreach ($planes as $plan)<option value="{{ $plan->id }}">{{ $plan->nombre }} · {{ $plan->duracion_dias }} días · ${{ number_format($plan->precio, 2) }}</option>@endforeach</select></label>
            <div class="grid gap-4 sm:grid-cols-2"><label class="block"><span class="mb-1.5 block text-sm font-semibold text-slate-300">Método de pago</span><select name="metodo_pago" required class="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-sm outline-none focus:border-teal-300/60"><option value="efectivo">Efectivo</option><option value="tarjeta">Tarjeta</option><option value="transferencia">Transferencia</option></select></label><label class="block"><span class="mb-1.5 block text-sm font-semibold text-slate-300">Referencia</span><input name="referencia" class="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-sm outline-none focus:border-teal-300/60"></label></div>
            <button class="w-full rounded-xl bg-teal-300 px-4 py-3 font-bold text-slate-950 hover:bg-teal-200">Confirmar renovación</button>
        </form>
    </div>
</div>
