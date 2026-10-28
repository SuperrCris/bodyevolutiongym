<details
    class="col-span-2 rounded-xl border border-white/10 bg-slate-950/30 px-3 py-2"
    @if (old('_vigencia_membresia_id') == $membresia->id) open @endif
>
    <summary class="cursor-pointer text-sm font-semibold text-slate-300 transition hover:text-teal-200">
        Editar vigencia
    </summary>
    <form method="POST" action="{{ route('membresias.vigencia.update', $membresia) }}" class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
        @csrf
        @method('PUT')
        <input type="hidden" name="_vigencia_membresia_id" value="{{ $membresia->id }}">
        <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-slate-400">Válida hasta</span>
            <input
                type="date"
                name="finalizacion"
                value="{{ old('_vigencia_membresia_id') == $membresia->id ? old('finalizacion', $membresia->finalizacion?->format('Y-m-d')) : $membresia->finalizacion?->format('Y-m-d') }}"
                min="{{ now()->toDateString() }}"
                required
                class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none focus:border-teal-300/60"
            >
        </label>
        <button type="submit" class="button-primary px-4 py-2.5 text-sm transition">
            Guardar fecha
        </button>
        @if (old('_vigencia_membresia_id') == $membresia->id)
            @error('finalizacion')
                <p class="text-sm text-rose-300" role="alert">{{ $message }}</p>
            @enderror
        @endif
    </form>
</details>
