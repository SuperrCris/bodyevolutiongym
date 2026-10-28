<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="mt-6 space-y-4">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach (['nombre' => 'Nombre', 'apellido' => 'Apellido', 'email' => 'Correo', 'telefono' => 'Teléfono', 'fecha_nacimiento' => 'Fecha de nacimiento'] as $field => $label)
            <label class="block {{ $field === 'email' ? 'sm:col-span-2' : '' }}">
                <span class="mb-1.5 block text-sm font-semibold text-slate-300">{{ $label }}</span>
                <input type="{{ $field === 'email' ? 'email' : ($field === 'fecha_nacimiento' ? 'date' : ($field === 'foto' ? 'file' : 'text')) }}" name="{{ $field }}" value="{{ old($field, $cliente?->{$field}) }}" class="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-sm outline-none focus:border-teal-300/60" {{ in_array($field, ['nombre', 'apellido']) ? 'required' : '' }}>
            </label>
        @endforeach
        <label class="block sm:col-span-2">
            <span class="mb-1.5 block text-sm font-semibold text-slate-300">Foto del cliente</span>
            <div class="flex flex-col gap-3 sm:flex-row">
                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" data-foto-input class="min-w-0 flex-1 rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-sm outline-none file:mr-3 file:rounded-lg file:border-0 file:bg-teal-300 file:px-3 file:py-2 file:font-semibold file:text-slate-950 focus:border-teal-300/60">
                <button type="button" data-open-camera class="shrink-0 rounded-xl border border-indigo-300/40 px-4 py-3 text-sm font-semibold text-indigo-200 hover:border-indigo-200">Abrir cámara</button>
            </div>
            <p class="mt-1 text-xs text-slate-400">Elige una imagen o usa la cámara para capturarla. Máximo 5 MB.</p>
            <img data-foto-preview src="{{ $cliente?->foto ? asset('storage/'.$cliente->foto) : '' }}" alt="Vista previa de la foto del cliente" class="{{ $cliente?->foto ? '' : 'hidden' }} mt-3 h-24 w-24 rounded-2xl object-cover ring-1 ring-white/10">
        </label>
    </div>
    @if ($cliente === null)
        <button type="submit" name="registrar_huella_despues" value="1" class="w-full rounded-xl border border-indigo-300/40 px-4 py-3 font-bold text-indigo-200 hover:border-indigo-200 hover:bg-indigo-300/10">Guardar y registrar huella</button>
    @endif
    <button type="submit" class="button-primary w-full px-4 py-3">Guardar cliente</button>
</form>
