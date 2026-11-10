@php($pago = $membresia->pagos->first())
<details
    class="col-span-2 rounded-xl border border-white/10 bg-slate-950/30 px-3 py-2"
    @if (old('_membresia_id') == $membresia->id) open @endif
>
    <summary class="cursor-pointer text-sm font-semibold text-slate-300 transition hover:text-teal-200">
        Editar membresía e importe
    </summary>
    <form method="POST" action="{{ route('membresias.update', $membresia) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
        @csrf
        @method('PUT')
        <input type="hidden" name="_membresia_id" value="{{ $membresia->id }}">
        <label class="block sm:col-span-2">
            <span class="mb-1.5 block text-xs font-semibold text-slate-400">Plan</span>
            <select name="tipo_membresia_id" required class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none focus:border-teal-300/60">
                @if ($membresia->plan && ! $planes->contains('id', $membresia->tipo_membresia_id))
                    <option value="{{ $membresia->tipo_membresia_id }}" @selected(old('tipo_membresia_id', $membresia->tipo_membresia_id) == $membresia->tipo_membresia_id)>{{ $membresia->plan->nombre }} (inactivo)</option>
                @endif
                @foreach ($planes as $plan)
                    <option value="{{ $plan->id }}" @selected(old('tipo_membresia_id', $membresia->tipo_membresia_id) == $plan->id)>{{ $plan->nombre }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-slate-400">Inicio</span>
            <input
                type="date"
                name="comienzo"
                value="{{ old('_membresia_id') == $membresia->id ? old('comienzo', $membresia->comienzo?->format('Y-m-d')) : $membresia->comienzo?->format('Y-m-d') }}"
                required
                class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none focus:border-teal-300/60"
            >
        </label>
        <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-slate-400">Válida hasta</span>
            <input
                type="date"
                name="finalizacion"
                value="{{ old('_membresia_id') == $membresia->id ? old('finalizacion', $membresia->finalizacion?->format('Y-m-d')) : $membresia->finalizacion?->format('Y-m-d') }}"
                required
                class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none focus:border-teal-300/60"
            >
        </label>
        @if ($pago)
            <label class="block sm:col-span-2">
                <span class="mb-1.5 block text-xs font-semibold text-slate-400">Importe pagado</span>
                <input
                    type="number"
                    name="monto"
                    min="0"
                    max="99999999.99"
                    step="0.01"
                    value="{{ old('_membresia_id') == $membresia->id ? old('monto', $pago->monto) : $pago->monto }}"
                    required
                    class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none focus:border-teal-300/60"
                >
            </label>
        @else
            <p class="sm:col-span-2 text-xs text-slate-400">Esta membresía no tiene un pago asociado; se pueden corregir el plan y las fechas.</p>
        @endif
        <button type="submit" class="button-primary px-4 py-2.5 text-sm transition sm:col-span-2">
            Guardar cambios
        </button>
        @if (old('_membresia_id') == $membresia->id)
            @foreach (['tipo_membresia_id', 'comienzo', 'finalizacion', 'monto'] as $field)
                @error($field)
                    <p class="text-sm text-rose-300 sm:col-span-2" role="alert">{{ $message }}</p>
                @enderror
            @endforeach
        @endif
    </form>
</details>
