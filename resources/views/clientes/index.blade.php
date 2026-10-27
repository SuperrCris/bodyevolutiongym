@extends('layouts.app')

@section('title', 'Clientes | Body Evolution Gym')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col justify-between gap-5 md:flex-row md:items-end">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-teal-300">Centro de control</p>
            <h1 class="mt-2 text-4xl font-black tracking-tight sm:text-5xl">Clientes</h1>
            <p class="mt-3 max-w-2xl text-slate-400">Gestiona altas, ediciones y renovaciones sin abandonar tu flujo de trabajo.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="button" data-verificar-huella class="rounded-2xl border border-teal-300/40 px-5 py-3 font-bold text-teal-200 transition hover:border-teal-200 hover:text-teal-100">Verificar huella</button>
            <button type="button" data-open-modal="crearCliente" class="rounded-2xl bg-teal-300 px-5 py-3 font-bold text-slate-950 shadow-lg shadow-teal-950/20 transition hover:bg-teal-200">+ Nuevo cliente</button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
            <p class="text-sm text-slate-400">Clientes registrados</p>
            <p id="totalClientes" class="mt-2 text-3xl font-black">{{ $clientes->total() }}</p>
        </div>
        <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
            <p class="text-sm text-slate-400">Membresías activas</p>
            <p id="membresiasActivas" class="mt-2 text-3xl font-black">—</p>
        </div>
        <div class="rounded-3xl border border-teal-300/20 bg-teal-400/10 p-5">
            <p class="text-sm text-teal-100/70">Sincronización</p>
            <p class="mt-2 flex items-center gap-2 text-lg font-bold text-teal-100"><span class="size-2 rounded-full bg-teal-300"></span> En línea</p>
            <p class="mt-1 text-xs text-teal-100/60">Actualiza actividad cada 15 s</p>
        </div>
    </div>

    <div class="rounded-3xl border border-white/10 bg-slate-900/70 p-5">
        <form method="GET" class="flex flex-col gap-3 sm:flex-row">
            <input name="buscar" value="{{ request('buscar') }}" class="min-w-0 flex-1 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm outline-none focus:border-teal-300/60" placeholder="Buscar por nombre o documento...">
            <button class="rounded-2xl border border-white/10 px-5 py-3 text-sm font-semibold text-slate-300 transition hover:border-teal-300/50 hover:text-teal-200">Buscar</button>
        </form>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($clientes as $cliente)
            @php($membresia = $cliente->membresias->first())
            @php($tieneHuella = $cliente->credencialesBiometricas->isNotEmpty())
            <article class="rounded-3xl border border-white/10 bg-white/[0.045] p-5 transition hover:border-teal-300/30">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        @if ($cliente->foto)
                            <img src="{{ asset('storage/'.$cliente->foto) }}" alt="Foto de {{ $cliente->nombre }} {{ $cliente->apellido }}" class="size-12 rounded-2xl object-cover">
                        @else
                            <div class="grid size-12 place-items-center rounded-2xl bg-slate-800 text-lg font-black text-teal-300">{{ strtoupper(substr($cliente->nombre, 0, 1).substr($cliente->apellido, 0, 1)) }}</div>
                        @endif
                        <div>
                            <h2 class="font-bold">{{ $cliente->nombre }} {{ $cliente->apellido }}</h2>
                            <p class="mt-1 text-sm text-slate-400">{{ $cliente->documento ?: 'Sin documento' }} · {{ $cliente->telefono ?: 'Sin teléfono' }}</p>
                        </div>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $membresia?->estaActiva() ? 'bg-teal-300/15 text-teal-200' : 'bg-amber-300/15 text-amber-200' }}">{{ $membresia?->estaActiva() ? 'Activo' : 'Sin plan activo' }}</span>
                </div>
                <div class="mt-5 grid gap-3 rounded-2xl bg-slate-950/50 p-4 text-sm sm:grid-cols-2">
                    <div><p class="text-slate-500">Plan actual</p><p class="mt-1 font-semibold">{{ $membresia?->plan?->nombre ?: '—' }}</p></div>
                    <div><p class="text-slate-500">Vigencia</p><p class="mt-1 font-semibold">{{ $membresia?->finalizacion?->format('d/m/Y') ?: '—' }}</p></div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm">
                    <div>
                        <p class="text-slate-500">Huella digital</p>
                        <p data-huella-estado="{{ $cliente->id }}" class="mt-1 font-semibold {{ $tieneHuella ? 'text-teal-200' : 'text-slate-300' }}">{{ $tieneHuella ? 'Registrada' : 'Sin registrar' }}</p>
                    </div>
                    <span data-huella-indicador="{{ $cliente->id }}" class="size-3 rounded-full {{ $tieneHuella ? 'bg-teal-300' : 'bg-slate-600' }}"></span>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="button" data-open-modal="editar-{{ $cliente->id }}" class="rounded-xl border border-white/10 px-3 py-2 text-sm font-semibold text-slate-300 hover:border-teal-300/50 hover:text-teal-200">Editar</button>
                    <button type="button" data-open-modal="renovar-{{ $cliente->id }}" class="rounded-xl bg-teal-300 px-3 py-2 text-sm font-bold text-slate-950 hover:bg-teal-200">Renovar membresía</button>
                    <button type="button" data-enrolar-huella="{{ $cliente->id }}" class="rounded-xl border border-indigo-300/40 px-3 py-2 text-sm font-semibold text-indigo-200 hover:border-indigo-200">{{ $tieneHuella ? 'Actualizar huella' : 'Registrar huella' }}</button>
                </div>
            </article>
            @include('clientes.partials.editar', ['cliente' => $cliente])
            @include('clientes.partials.renovar', ['cliente' => $cliente, 'planes' => $planes])
        @empty
            <div class="rounded-3xl border border-dashed border-white/15 p-10 text-center text-slate-400 lg:col-span-2">No hay clientes que coincidan con la búsqueda.</div>
        @endforelse
    </div>
    <div>{{ $clientes->links() }}</div>
</div>

<div id="crearCliente" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm">
    <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl">
        <div class="flex items-center justify-between"><h2 class="text-xl font-bold">Nuevo cliente</h2><button type="button" data-close-modal class="text-2xl text-slate-500 hover:text-white">×</button></div>
        @include('clientes.partials.formulario', ['action' => route('clientes.store'), 'method' => 'POST', 'cliente' => null])
    </div>
</div>

<div id="camaraCliente" data-camera-modal class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-950/90 p-6 backdrop-blur-sm">
    <div class="w-full max-w-2xl rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold">Capturar foto</h2>
                <p class="mt-1 text-sm text-slate-400">Acomoda el rostro dentro del encuadre y presiona capturar.</p>
            </div>
            <button type="button" data-close-camera class="text-2xl text-slate-500 hover:text-white" aria-label="Cerrar cámara">×</button>
        </div>
        <video data-camera-video class="mt-5 aspect-video w-full rounded-2xl bg-black object-cover" autoplay playsinline></video>
        <canvas data-camera-canvas class="hidden"></canvas>
        <div class="mt-5 flex justify-end gap-3">
            <button type="button" data-close-camera class="rounded-xl border border-white/10 px-4 py-3 font-semibold text-slate-300 hover:border-white/30">Cancelar</button>
            <button type="button" data-capture-photo class="rounded-xl bg-teal-300 px-5 py-3 font-bold text-slate-950 hover:bg-teal-200">Capturar foto</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    document.querySelectorAll('[data-open-modal]').forEach((button) => button.addEventListener('click', () => {
        const modal = document.getElementById(button.dataset.openModal);
        modal.classList.remove('hidden'); modal.classList.add('flex');
    }));
    document.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', () => {
        const modal = button.closest('[data-modal]'); modal.classList.add('hidden'); modal.classList.remove('flex');
    }));
    document.querySelectorAll('[data-foto-input]').forEach((input) => input.addEventListener('change', () => {
        const preview = input.closest('form').querySelector('[data-foto-preview]');
        const file = input.files?.[0];
        if (!file) return;
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('hidden');
    }));
    const cameraModal = document.querySelector('[data-camera-modal]');
    const cameraVideo = cameraModal?.querySelector('[data-camera-video]');
    const cameraCanvas = cameraModal?.querySelector('[data-camera-canvas]');
    let cameraStream = null;
    let cameraInput = null;

    function stopCamera() {
        cameraStream?.getTracks().forEach((track) => track.stop());
        cameraStream = null;
        if (cameraVideo) cameraVideo.srcObject = null;
        cameraInput = null;
        cameraModal?.classList.add('hidden');
        cameraModal?.classList.remove('flex');
    }

    document.querySelectorAll('[data-open-camera]').forEach((button) => button.addEventListener('click', async () => {
        cameraInput = button.closest('form')?.querySelector('[data-foto-input]');
        if (!cameraInput || !cameraModal || !cameraVideo || !cameraCanvas) return;

        if (!navigator.mediaDevices?.getUserMedia) {
            window.alert('Este navegador no permite acceder a la cámara. Puedes seleccionar una imagen.');
            return;
        }

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user' },
                audio: false,
            });
            cameraVideo.srcObject = cameraStream;
            cameraModal.classList.remove('hidden');
            cameraModal.classList.add('flex');
        } catch (error) {
            stopCamera();
            window.alert('No fue posible activar la cámara. Verifica el permiso del navegador y que no esté siendo usada por otra aplicación.');
        }
    }));

    cameraModal?.querySelectorAll('[data-close-camera]').forEach((button) => button.addEventListener('click', stopCamera));
    cameraModal?.querySelector('[data-capture-photo]')?.addEventListener('click', () => {
        if (!cameraInput || !cameraVideo || !cameraCanvas || cameraVideo.videoWidth === 0) {
            window.alert('La cámara todavía no está lista. Espera un momento e inténtalo de nuevo.');
            return;
        }

        cameraCanvas.width = cameraVideo.videoWidth;
        cameraCanvas.height = cameraVideo.videoHeight;
        cameraCanvas.getContext('2d').drawImage(cameraVideo, 0, 0);
        cameraCanvas.toBlob((blob) => {
            if (!blob || !cameraInput) {
                window.alert('No fue posible capturar la imagen.');
                return;
            }

            const archivo = new File([blob], `cliente-${Date.now()}.jpg`, { type: 'image/jpeg' });
            const archivos = new DataTransfer();
            archivos.items.add(archivo);
            cameraInput.files = archivos.files;
            cameraInput.dispatchEvent(new Event('change', { bubbles: true }));
            stopCamera();
        }, 'image/jpeg', 0.9);
    });
    setTimeout(() => document.querySelector('[data-toast]')?.remove(), 5000);
    async function actualizarResumen() {
        const response = await fetch('{{ route('clientes.resumen') }}', { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        document.getElementById('totalClientes').textContent = data.clientes;
        document.getElementById('membresiasActivas').textContent = data.membresias_activas;
    }
    async function solicitarHuella(url, options = {}) {
        const response = await fetch(url, {
            ...options,
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || data.error || 'No fue posible comunicarse con el lector.');
        return data;
    }
    document.querySelectorAll('[data-enrolar-huella]').forEach((button) => button.addEventListener('click', async () => {
        button.disabled = true;
        button.textContent = 'Coloca el dedo...';
        try {
            await solicitarHuella(`/api/v1/customers/${button.dataset.enrolarHuella}/biometrics/enroll`, {
                method: 'POST',
                body: JSON.stringify({ terminal_identifier: '' }),
            });
            button.textContent = 'Huella registrada';
            document.querySelector(`[data-huella-estado="${button.dataset.enrolarHuella}"]`).textContent = 'Registrada';
            document.querySelector(`[data-huella-estado="${button.dataset.enrolarHuella}"]`).classList.remove('text-slate-300');
            document.querySelector(`[data-huella-estado="${button.dataset.enrolarHuella}"]`).classList.add('text-teal-200');
            document.querySelector(`[data-huella-indicador="${button.dataset.enrolarHuella}"]`).classList.remove('bg-slate-600');
            document.querySelector(`[data-huella-indicador="${button.dataset.enrolarHuella}"]`).classList.add('bg-teal-300');
            button.classList.add('text-teal-200');
        } catch (error) {
            button.disabled = false;
            button.textContent = 'Registrar huella';
            window.alert(error.message);
        }
    }));
    document.querySelector('[data-verificar-huella]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        button.textContent = 'Coloca el dedo...';
        try {
            const captura = await solicitarHuella('/api/v1/biometrics/capture', {
                method: 'POST',
                body: JSON.stringify({ terminal_identifier: '' }),
            });
            const resultado = await solicitarHuella('/api/v1/biometrics/verify', {
                method: 'POST',
                body: JSON.stringify({ capture_token: captura.capture_token, terminal_identifier: '' }),
            });
            window.alert(resultado.matched ? `Cliente identificado: #${resultado.id_cliente}` : 'Huella no registrada.');
        } catch (error) {
            window.alert(error.message);
        } finally {
            button.disabled = false;
            button.textContent = 'Verificar huella';
        }
    });
    actualizarResumen();
    setInterval(actualizarResumen, 15000);
</script>
@endpush
