@php($fotoInputId = 'foto-cliente-'.($cliente?->id ?? 'nuevo'))
@php($fotoDocumentoInputId = 'foto-documento-cliente-'.($cliente?->id ?? 'nuevo'))
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
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <label for="{{ $fotoDocumentoInputId }}" class="text-sm font-semibold text-slate-300">Imagen del documento</label>
            <span class="rounded-full border border-white/10 px-2.5 py-1 text-xs font-medium text-slate-400">Opcional</span>
        </div>
        <div class="relative aspect-[1.6/1] w-full overflow-hidden rounded-2xl border border-white/10 bg-slate-950/70">
            <img
                data-documento-preview
                src="{{ $cliente?->foto_documento ? route('clientes.documento', $cliente) : '' }}"
                alt="{{ $cliente?->foto_documento ? 'Documento de '.$cliente->nombre.' '.$cliente->apellido : 'Vista previa del documento' }}"
                class="{{ $cliente?->foto_documento ? '' : 'hidden' }} absolute inset-0 size-full object-contain p-2"
            >
            <div data-documento-placeholder class="{{ $cliente?->foto_documento ? 'hidden' : '' }} absolute inset-0 grid place-items-center gap-2 p-4 text-center">
                <svg class="size-12 text-slate-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 3.75h9l5 5v11.5H5v-16.5Z" stroke="currentColor" stroke-linejoin="round" stroke-width="1.5" />
                    <path d="M14 4v5h5M8 13h8M8 16h8" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" />
                </svg>
                <span class="text-xs text-slate-400">La imagen del documento aparecerá aquí</span>
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <input id="{{ $fotoDocumentoInputId }}" type="file" name="foto_documento" accept="image/jpeg,image/png,image/webp" data-image-preview-input="documento" data-camera-input="documento" class="peer sr-only">
            <button type="button" data-open-camera="documento" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-indigo-300/40 bg-indigo-300/10 px-4 py-2.5 text-sm font-semibold text-indigo-200 transition hover:border-indigo-200 hover:bg-indigo-300/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-200">
                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4 7.5h3l1.5-2h7l1.5 2h3A1.5 1.5 0 0 1 21.5 9v9a1.5 1.5 0 0 1-1.5 1.5H4A1.5 1.5 0 0 1 2.5 18V9A1.5 1.5 0 0 1 4 7.5Z" stroke="currentColor" stroke-linejoin="round" stroke-width="1.7" />
                    <circle cx="12" cy="13" r="3.5" stroke="currentColor" stroke-width="1.7" />
                </svg>
                <span>Tomar foto</span>
            </button>
            <label for="{{ $fotoDocumentoInputId }}" class="inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl border border-teal-300/40 bg-teal-300/10 px-4 py-2.5 text-sm font-semibold text-teal-200 transition hover:border-teal-200 hover:bg-teal-300/15 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-teal-200">
                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M13.5 3.5H6A1.5 1.5 0 0 0 4.5 5v14A1.5 1.5 0 0 0 6 20.5h12a1.5 1.5 0 0 0 1.5-1.5V9l-6-5.5Z" stroke="currentColor" stroke-linejoin="round" stroke-width="1.7" />
                    <path d="M13 4v5h5M7.5 16l2.5-2.5 2 2 1.5-1.5 3 3" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                </svg>
                <span>Elegir archivo</span>
            </label>
        </div>
        <p class="text-xs text-slate-400">Puedes tomar una foto o elegir una imagen. Máximo 5 MB.</p>
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
