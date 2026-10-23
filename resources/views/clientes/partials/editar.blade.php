<div id="editar-{{ $cliente->id }}" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm">
    <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl">
        <div class="flex items-center justify-between"><h2 class="text-xl font-bold">Editar cliente</h2><button data-close-modal class="text-2xl text-slate-500 hover:text-white">×</button></div>
        @include('clientes.partials.formulario', ['action' => route('clientes.update', $cliente), 'method' => 'PUT', 'cliente' => $cliente])
    </div>
</div>
