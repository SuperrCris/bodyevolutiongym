@extends('layouts.app')

@section('title', 'Clientes | Body Evolution Gym')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col justify-between gap-5 md:flex-row md:items-end">
        <div>
            <h2 class="mt-2 text-3xl font-black text-white">Clientes</h2>

        </div>
        <div class="flex flex-wrap gap-3">
          <button type="button" data-verificar-huella class="rounded-2xl border border-teal-300/40 px-5 py-3 font-bold text-teal-200 transition hover:border-teal-200 hover:text-teal-100">Verificar huella</button>
            <button type="button" data-open-modal="crearCliente" class="rounded-2xl bg-teal-300 px-5 py-3 font-bold text-slate-950 shadow-lg shadow-teal-950/20 transition hover:bg-teal-200">+ Nuevo cliente</button>
        </div>
    </div>

    <!--
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
-->
    <div class="panel p-5">
        <form method="GET" class="flex flex-col gap-3 sm:flex-row">
            <input name="buscar" value="{{ request('buscar') }}" class="min-w-0 flex-1 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm outline-none focus:border-teal-300/60" placeholder="Buscar por nombre...">
            <button class="rounded-2xl border border-white/10 px-5 py-3 text-sm font-semibold text-slate-300 transition hover:border-teal-300/50 hover:text-teal-200">Buscar</button>
            @if (request()->filled('buscar'))
                <a href="{{ route('clientes.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-white/10 px-5 py-3 text-sm font-semibold text-slate-300 transition hover:border-amber-300/40 hover:text-amber-200">Limpiar búsqueda</a>
            @endif
        </form>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($clientes as $cliente)
            @php($membresia = $cliente->membresias->first())
            @php($tieneHuella = $cliente->credencialesBiometricas->isNotEmpty())
            <article class="flex h-full flex-col gap-4 rounded-3xl border border-white/10 bg-white/[0.045] p-4 transition hover:border-teal-300/30 hover:bg-white/[0.06]">
                <div class="flex min-w-0 items-start gap-3">
                    @if ($cliente->foto)
                        <img src="{{ asset('storage/'.$cliente->foto) }}" alt="Foto de {{ $cliente->nombre }} {{ $cliente->apellido }}" class="size-12 shrink-0 rounded-2xl object-cover ring-1 ring-white/10">
                    @else
                        <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-slate-800 text-base font-black text-teal-300">{{ strtoupper(substr($cliente->nombre, 0, 1).substr($cliente->apellido, 0, 1)) }}</div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-base font-bold" title="#{{ $cliente->id }} - {{ $cliente->nombre }} {{ $cliente->apellido }}">#{{ $cliente->id }} - {{ $cliente->nombre }} {{ $cliente->apellido }}</h2>
                        <p class="mt-1 truncate text-sm text-slate-400" title="{{ $cliente->telefono ?: 'Sin teléfono' }}">{{ $cliente->telefono ?: 'Sin teléfono' }}</p>
                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $membresia?->estaActiva() ? 'bg-teal-300/15 text-teal-200' : 'bg-amber-300/15 text-amber-200' }}">{{ $membresia?->estaActiva() ? 'Membresía activa' : 'Sin plan activo' }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 rounded-2xl bg-slate-950/50 p-3">
                    <div class="min-w-0">
                        <p class="text-xs text-slate-500">Plan actual</p>
                        <p class="mt-1 truncate text-sm font-semibold" title="{{ $membresia?->plan?->nombre ?: '—' }}">{{ $membresia?->plan?->nombre ?: '—' }}</p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-slate-500">Vigencia</p>
                        <p class="mt-1 text-sm font-semibold">{{ $membresia?->finalizacion?->format('d/m/Y') ?: '—' }}</p>
                    </div>
                    @if ($membresia && auth()->user()->esAdministrador())
                        @include('clientes.partials.vigencia', ['membresia' => $membresia])
                    @endif
                    <div class="col-span-2 flex items-center gap-2 border-t border-white/10 pt-3">
                        <span data-huella-indicador="{{ $cliente->id }}" class="relative grid size-6 shrink-0 place-items-center">
                            <svg class="size-5 text-slate-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M5 11a7 7 0 0 1 14 0v2M7.5 10.5a4.5 4.5 0 0 1 9 0v3M10 10.5a2 2 0 0 1 4 0v4a8 8 0 0 1-1.25 4.3M12 12v3a10 10 0 0 1-1.5 5.5M9 13v1a5 5 0 0 1-.75 2.5M17 13v1.5a12 12 0 0 1-1 4" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                            </svg>
                            <span class="absolute -bottom-0.5 -right-0.5 grid size-3.5 place-items-center rounded-full bg-slate-900">
                                <svg data-huella-check class="{{ $tieneHuella ? '' : 'hidden' }} size-2.5 text-teal-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m4.5 10.5 3.75 3.75L15.75 6.75" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
                                </svg>
                                <svg data-huella-cross class="{{ $tieneHuella ? 'hidden' : '' }} size-2.5 text-rose-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m6 6 8 8m0-8-8 8" stroke="currentColor" stroke-linecap="round" stroke-width="2" />
                                </svg>
                            </span>
                        </span>
                        <span data-huella-estado="{{ $cliente->id }}" class="text-sm font-semibold {{ $tieneHuella ? 'text-teal-200' : 'text-slate-300' }}">{{ $tieneHuella ? 'Huella registrada' : 'Huella sin registrar' }}</span>
                    </div>
                </div>
                <div class="mt-auto flex gap-2 border-t border-white/10 pt-3">
                    <button type="button" data-open-modal="editar-{{ $cliente->id }}" class="min-h-11 flex-1 rounded-xl border border-white/10 px-3 py-2 text-sm font-semibold text-slate-300 transition hover:border-teal-300/50 hover:text-teal-200">Editar</button>
                    <button type="button" data-open-modal="renovar-{{ $cliente->id }}" class="button-primary min-h-11 flex-1 px-3 py-2 text-sm transition">Renovar</button>
                </div>
            </article>
            @include('clientes.partials.editar', ['cliente' => $cliente])
            @include('clientes.partials.renovar', ['cliente' => $cliente, 'planes' => $planes, 'tieneHuella' => $tieneHuella])
        @empty
            <div class="col-span-full rounded-3xl border border-dashed border-white/15 p-10 text-center text-slate-400">No hay clientes que coincidan con la búsqueda.</div>
        @endforelse
    </div>
    <div>{{ $clientes->links() }}</div>
</div>

@if ($clienteParaRenovar && ! $clientes->getCollection()->contains('id', $clienteParaRenovar->id))
    @include('clientes.partials.renovar', ['cliente' => $clienteParaRenovar, 'planes' => $planes])
@endif

<div id="crearCliente" data-modal class="modal-backdrop z-50 {{ $clienteParaEnrolar ? 'flex' : 'hidden' }} bg-slate-950/80" @if ($clienteParaEnrolar) data-refresh-after-close @endif>
    <div class="modal-panel max-h-[calc(100vh-3rem)] w-full max-w-lg overflow-y-auto">
        @if ($clienteParaEnrolar)
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold">Registrar huella</h2>
                    <p class="mt-1 text-sm text-slate-400">Cliente: {{ $clienteParaEnrolar->nombre }} {{ $clienteParaEnrolar->apellido }}</p>
                </div>
                <button type="button" data-close-modal class="text-2xl text-slate-500 hover:text-white" aria-label="Cerrar">×</button>
            </div>
            @include('clientes.partials.enrolar-huella', [
                'clienteId' => $clienteParaEnrolar->id,
                'tieneHuella' => $clienteParaEnrolar->credencialesBiometricas->isNotEmpty(),
                'requiereGuardar' => false,
            ])
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <button type="button" data-open-membership-modal="{{ $clienteParaEnrolar->id }}" class="button-primary w-full px-4 py-3">Comprar membresía</button>
                <button type="button" data-close-modal class="w-full rounded-xl border border-white/10 px-4 py-3 text-sm font-semibold text-slate-300 transition hover:border-white/30">Salir</button>
            </div>
        @else
            <div class="flex items-center justify-between"><h2 class="text-xl font-bold">Nuevo cliente</h2><button type="button" data-close-modal class="text-2xl text-slate-500 hover:text-white">×</button></div>
            @include('clientes.partials.formulario', ['action' => route('clientes.store'), 'method' => 'POST', 'cliente' => null])
        @endif
    </div>
</div>

<div id="camaraCliente" data-camera-modal class="modal-backdrop z-[60] hidden bg-slate-950/90">
    <div class="modal-panel w-full max-w-2xl">
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
            <button type="button" data-capture-photo class="button-primary px-5 py-3">Capturar foto</button>
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
    const clienteIdParaRenovar = new URLSearchParams(window.location.search).get('abrir_renovacion');
    if (/^\d+$/.test(clienteIdParaRenovar ?? '')) {
        const modalRenovacion = document.getElementById(`renovar-${clienteIdParaRenovar}`);
        if (modalRenovacion) {
            modalRenovacion.classList.remove('hidden');
            modalRenovacion.classList.add('flex');
            modalRenovacion.querySelector('select[name="tipo_membresia_id"]')?.focus();

            const urlActual = new URL(window.location.href);
            urlActual.searchParams.delete('abrir_renovacion');
            window.history.replaceState({}, '', urlActual);
        }
    }
    document.querySelectorAll('[data-open-membership-modal]').forEach((button) => button.addEventListener('click', () => {
        const enrollmentModal = button.closest('[data-modal]');
        const membershipModal = document.getElementById(`renovar-${button.dataset.openMembershipModal}`);

        enrollmentModal.classList.add('hidden');
        enrollmentModal.classList.remove('flex');
        membershipModal.classList.remove('hidden');
        membershipModal.classList.add('flex');
    }));
    document.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', () => {
        const modal = button.closest('[data-modal]');
        const refreshAfterClose = modal.hasAttribute('data-refresh-after-close');
        modal.classList.add('hidden');
        modal.classList.remove('flex');

        if (refreshAfterClose) {
            window.location.reload();
        }
    }));
    document.querySelectorAll('[data-foto-input]').forEach((input) => input.addEventListener('change', () => {
        const preview = input.closest('form').querySelector('[data-foto-preview]');
        const placeholder = input.closest('form').querySelector('[data-foto-placeholder]');
        const file = input.files?.[0];
        if (!file) return;
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
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
    function actualizarProgresoEnrolamiento(clienteId, muestras, total) {
        const pasosCompletados = total > 0 ? Math.min(4, Math.floor((muestras / total) * 4)) : 0;
        document.querySelectorAll(`[data-progreso-enrolamiento="${clienteId}"]`).forEach((panel) => {
            panel.querySelectorAll('[data-paso-enrolamiento]').forEach((circulo) => {
                circulo.dataset.completed = String(Number(circulo.dataset.pasoEnrolamiento) <= pasosCompletados);
            });
        });
    }
    function mostrarMensajeEnrolamiento(clienteId, mensaje) {
        document.querySelectorAll(`[data-progreso-enrolamiento="${clienteId}"]`).forEach((panel) => {
            panel.querySelector('[data-mensaje-enrolamiento]').textContent = mensaje;
        });
    }
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-enrolar-huella]');
        if (!button) return;
        const clienteId = button.dataset.enrolarHuella;
        const enrollmentButtons = document.querySelectorAll(`[data-enrolar-huella="${clienteId}"]`);
        enrollmentButtons.forEach((action) => {
            action.disabled = true;
            action.textContent = 'Esperando lector...';
        });
        actualizarProgresoEnrolamiento(clienteId, 0, 4);
        mostrarMensajeEnrolamiento(clienteId, 'Coloca el dedo en el lector y retíralo cuando termine cada captura.');
        try {
            await solicitarHuella(`/api/v1/customers/${clienteId}/biometrics/enroll/start`, {
            method: 'POST',
            body: JSON.stringify({ terminal_identifier: '' }),
        });

            while (true) {
                const progreso = await solicitarHuella(`/api/v1/customers/${clienteId}/biometrics/enroll/progress`, {
                    method: 'GET',
                });
                actualizarProgresoEnrolamiento(clienteId, progreso.muestras, progreso.total);
                mostrarMensajeEnrolamiento(clienteId, progreso.mensaje);

                if (progreso.estado === 'completado') {
                    break;
                }

                if (progreso.estado === 'error') {
                    throw new Error(progreso.error || progreso.mensaje || 'No se pudo registrar la huella.');
                }

                if (!progreso.en_curso) {
                    throw new Error('El lector dejó de reportar el enrolamiento. Inténtalo nuevamente.');
                }

                await new Promise((resolve) => setTimeout(resolve, 350));
            }

            await solicitarHuella(`/api/v1/customers/${clienteId}/biometrics/enroll/finish`, {
                method: 'POST',
                body: JSON.stringify({}),
            });
            actualizarProgresoEnrolamiento(clienteId, 4, 4);
            mostrarMensajeEnrolamiento(clienteId, 'Huella registrada correctamente.');
            enrollmentButtons.forEach((action) => {
                action.disabled = false;
                action.dataset.labelInicial = 'Actualizar huella';
                action.textContent = 'Actualizar huella';
                action.classList.remove('text-indigo-200');
                action.classList.add('text-teal-200');
            });
            document.querySelectorAll(`[data-huella-estado="${clienteId}"]`).forEach((estado) => {
                estado.textContent = 'Registrada';
                estado.classList.remove('text-slate-300');
                estado.classList.add('text-teal-200');
            });
            document.querySelectorAll(`[data-huella-indicador="${clienteId}"]`).forEach((indicador) => {
                indicador.querySelector('[data-huella-check]').classList.remove('hidden');
                indicador.querySelector('[data-huella-cross]').classList.add('hidden');
            });
        } catch (error) {
            enrollmentButtons.forEach((action) => {
                action.disabled = false;
                action.textContent = action.dataset.labelInicial;
            });
            mostrarMensajeEnrolamiento(clienteId, error.message);
            window.alert(error.message);
        }
    });
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
