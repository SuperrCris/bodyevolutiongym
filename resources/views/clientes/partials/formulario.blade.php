<form method="POST" action="{{ $action }}" class="mt-6 space-y-4">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach (['nombre' => 'Nombre', 'apellido' => 'Apellido', 'email' => 'Correo', 'telefono' => 'Teléfono', 'documento' => 'Documento', 'fecha_nacimiento' => 'Fecha de nacimiento'] as $field => $label)
            <label class="block {{ $field === 'email' ? 'sm:col-span-2' : '' }}">
                <span class="mb-1.5 block text-sm font-semibold text-slate-300">{{ $label }}</span>
                <input type="{{ $field === 'email' ? 'email' : ($field === 'fecha_nacimiento' ? 'date' : 'text') }}" name="{{ $field }}" value="{{ old($field, $cliente?->{$field}) }}" class="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-sm outline-none focus:border-teal-300/60" {{ in_array($field, ['nombre', 'apellido']) ? 'required' : '' }}>
            </label>
        @endforeach
    </div>
    <button class="w-full rounded-xl bg-teal-300 px-4 py-3 font-bold text-slate-950 hover:bg-teal-200">Guardar cliente</button>
</form>
