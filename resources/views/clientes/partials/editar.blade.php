<div id="editar-{{ $cliente->id }}" data-modal class="modal-backdrop z-50 hidden bg-slate-950/80">
    <div class="modal-panel max-h-[calc(100vh-3rem)] w-full max-w-lg overflow-y-auto">
        <div class="flex items-center justify-between"><h2 class="text-xl font-bold">Editar cliente</h2><button type="button" data-close-modal class="text-2xl text-slate-500 hover:text-white">×</button></div>
        @include('clientes.partials.formulario', [
            'action' => route('clientes.update', $cliente),
            'method' => 'PUT',
            'cliente' => $cliente,
            'tieneHuella' => $tieneHuella,
        ])
    </div>
</div>
