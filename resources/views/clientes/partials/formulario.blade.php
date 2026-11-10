@php($fotoInputId = 'foto-cliente-'.($cliente?->id ?? 'nuevo'))
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="mt-6 space-y-5">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div class="space-y-2">
        <span class="block text-center text-sm font-semibold text-slate-300">Foto</span>
        <div class="flex items-center justify-center gap-4">
            <button type="button" data-open-camera="foto" aria-label="Tomar foto con la cámara" class="grid size-11 shrink-0 place-items-center rounded-full border border-indigo-300/40 bg-indigo-300/10 text-indigo-200 transition hover:border-indigo-200 hover:bg-indigo-300/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-200">
                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4 7.5h3l1.5-2h7l1.5 2h3A1.5 1.5 0 0 1 21.5 9v9a1.5 1.5 0 0 1-1.5 1.5H4A1.5 1.5 0 0 1 2.5 18V9A1.5 1.5 0 0 1 4 7.5Z" stroke="currentColor" stroke-linejoin="round" stroke-width="1.7" />
                    <circle cx="12" cy="13" r="3.5" stroke="currentColor" stroke-width="1.7" />
                </svg>
            </button>
            <div class="grid size-36 shrink-0 place-items-center overflow-hidden rounded-full border-[3px] border-teal-300 bg-slate-950/70">
                <svg data-foto-placeholder class="{{ $cliente?->foto ? 'hidden' : '' }} size-20 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="currentColor" aria-hidden="true">
                    <circle cx="24" cy="15" r="10" />
                    <path d="M5 43c0-9.4 8.5-16 19-16s19 6.6 19 16v2H5v-2Z" />
                </svg>
                <img data-foto-preview src="{{ $cliente?->foto ? asset('storage/'.$cliente->foto) : '' }}" alt="Foto del cliente" class="{{ $cliente?->foto ? '' : 'hidden' }} size-full object-cover">
            </div>
            <input id="{{ $fotoInputId }}" type="file" name="foto" accept="image/jpeg,image/png,image/webp" data-image-preview-input="foto" data-camera-input="foto" class="peer sr-only">
            <label for="{{ $fotoInputId }}" aria-label="Elegir foto desde archivos" title="Elegir foto desde archivos" class="grid size-11 shrink-0 cursor-pointer place-items-center rounded-full border border-teal-300/40 bg-teal-300/10 text-teal-200 transition hover:border-teal-200 hover:bg-teal-300/15 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-teal-200">
                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M13.5 3.5H6A1.5 1.5 0 0 0 4.5 5v14A1.5 1.5 0 0 0 6 20.5h12a1.5 1.5 0 0 0 1.5-1.5V9l-6-5.5Z" stroke="currentColor" stroke-linejoin="round" stroke-width="1.7" />
                    <path d="M13 4v5h5M7.5 16l2.5-2.5 2 2 1.5-1.5 3 3" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                </svg>
            </label>
        </div>
        <p class="text-center text-xs text-slate-400">Usa la cámara o elige una imagen. Máximo 5 MB.</p>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach (['nombre' => 'Nombre', 'apellido' => 'Apellido', 'telefono' => 'Teléfono', 'direccion' => 'Dirección'] as $field => $label)
            <label class="block">
                <span class="mb-1.5 block text-sm font-semibold text-slate-300">{{ $label }}</span>
                <input type="text" name="{{ $field }}" value="{{ old($field, $cliente?->{$field}) }}" class="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-sm outline-none focus:border-teal-300/60" {{ in_array($field, ['nombre', 'apellido']) ? 'required' : '' }}>
            </label>
        @endforeach
    </div>
    <div class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-200">Documento de promoción</h3>
            <p class="mt-1 text-xs text-slate-400">Se removieron las fotos, ahora los datos se capturan manualmente</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1.5 block text-sm font-semibold text-slate-300">Institución</span>
                <input type="text" name="institucion_documento" maxlength="255" value="{{ old('institucion_documento', $cliente?->institucion_documento) }}" class="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-sm outline-none focus:border-teal-300/60">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-semibold text-slate-300">Vigencia</span>
                <input type="date" name="vigencia_documento" value="{{ old('vigencia_documento', $cliente?->vigencia_documento?->format('Y-m-d')) }}" class="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-sm outline-none focus:border-teal-300/60">
            </label>
        </div>
        <label class="block">
            <span class="mb-1.5 block text-sm font-semibold text-slate-300">Detalles</span>
            <textarea name="detalles_documento" maxlength="5000" rows="3" class="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-sm outline-none focus:border-teal-300/60">{{ old('detalles_documento', $cliente?->detalles_documento) }}</textarea>
        </label>
    </div>
    @if ($cliente === null)
        <div class="grid gap-3 sm:grid-cols-2">
            <button type="submit" class="w-full rounded-xl border border-indigo-300/40 px-4 py-3 text-sm font-bold text-indigo-200 transition hover:border-indigo-200 hover:bg-indigo-300/10">Guardar cliente</button>
            <button type="submit" name="registrar_huella_despues" value="1" class="button-primary w-full px-4 py-3">Guardar y asignar huella</button>
        </div>
    @else
        @include('clientes.partials.enrolar-huella', [
            'clienteId' => $cliente->id,
            'tieneHuella' => $tieneHuella ?? false,
            'requiereGuardar' => false,
        ])
        <button type="submit" class="button-primary w-full px-4 py-3">Guardar cliente</button>
    @endif
</form>
