@extends('layouts.app')

@section('title', 'Ventas | Body Evolution Gym')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="mt-2 text-3xl font-black text-white">Historial de ventas</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-400">Aquí puedes corregir el método de pago de cualquier venta. Los cambios quedan en auditoría.</p>
        </div>

        <section class="panel overflow-hidden shadow-xl">
            <div class="border-b border-white/10 px-5 py-4 sm:px-7">
                <h2 class="font-bold text-white">Pagos registrados</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Fecha</th>
                            <th class="px-5 py-3">Cliente / comprador</th>
                            <th class="px-5 py-3">Plan</th>
                            <th class="px-5 py-3">Método de pago</th>
                            <th class="px-5 py-3 text-right">Monto</th>
                            <th class="px-5 py-3">Referencia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($pagos as $pago)
                            @php
                                $metodoActual = match ($pago->metodo_pago) {
                                    'cash' => 'efectivo',
                                    'card' => 'tarjeta',
                                    'transfer' => 'transferencia',
                                    default => $pago->metodo_pago,
                                };
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-300">{{ $pago->created_at?->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-5 py-3 font-medium text-white">
                                    {{ $pago->id_cliente === null ? ($pago->nombre_comprador ?: 'Venta sin cliente') : ($pago->cliente ? trim($pago->cliente->nombre.' '.$pago->cliente->apellido) : 'Cliente eliminado') }}
                                </td>
                                <td class="px-5 py-3 text-slate-300">{{ $pago->plan?->nombre ?? $pago->membresia?->plan?->nombre ?? 'Membresía' }}</td>
                                <td class="px-5 py-3">
                                    <form method="POST" action="{{ route('ventas.metodo.update', $pago) }}" class="flex min-w-56 items-center gap-2">
                                        @csrf
                                        @method('PUT')
                                        <label class="sr-only" for="metodo-pago-{{ $pago->id }}">Método de pago del registro {{ $pago->id }}</label>
                                        <select id="metodo-pago-{{ $pago->id }}" name="metodo_pago" required class="field-control min-w-0 py-2">
                                            <option value="" disabled @selected(! array_key_exists($metodoActual, $metodosPago))>
                                                {{ array_key_exists($metodoActual, $metodosPago) ? 'Método actual' : 'Método actual: '.$pago->metodo_pago }}
                                            </option>
                                            @foreach ($metodosPago as $valor => $nombre)
                                                <option value="{{ $valor }}" @selected($metodoActual === $valor)>{{ $nombre }}</option>
                                            @endforeach
                                        </select>
                                        <button class="shrink-0 rounded-lg border border-teal-300/30 px-3 py-2 text-xs font-semibold text-teal-100 transition hover:bg-teal-300/10">Guardar</button>
                                    </form>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-teal-100">${{ number_format((float) $pago->monto, 2) }}</td>
                                <td class="px-5 py-3 text-slate-400">{{ $pago->referencia ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">No hay ventas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-white/10 px-5 py-4 sm:px-7">{{ $pagos->links() }}</div>
        </section>
    </div>
@endsection
