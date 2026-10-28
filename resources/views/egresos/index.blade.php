@extends('layouts.app')

@section('title', 'Salidas | Body Evolution Gym')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="mt-2 text-3xl font-black text-white">Registrar egreso</h1>
        </div>

        <section class="panel p-5 shadow-xl sm:p-7">
            <form method="POST" action="{{ route('egresos.store') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @csrf
                <label class="block md:col-span-2">
                    <span class="field-label">Concepto</span>
                    <input name="concepto" value="{{ old('concepto') }}" required maxlength="160" class="field-control" placeholder="Ej. Compra de artículos de limpieza">
                </label>
                <label class="block">
                    <span class="field-label">Categoría</span>
                    <input name="categoria" value="{{ old('categoria') }}" required maxlength="80" list="categorias-egreso" class="field-control" placeholder="Ej. Servicios">
                    <datalist id="categorias-egreso">
                        <option value="Servicios"></option>
                        <option value="Mantenimiento"></option>
                        <option value="Insumos"></option>
                        <option value="Nómina"></option>
                        <option value="Renta"></option>
                    </datalist>
                </label>
                <label class="block">
                    <span class="field-label">Monto</span>
                    <input type="number" name="monto" value="{{ old('monto') }}" required min="0.01" max="99999999.99" step="0.01" class="field-control">
                </label>
                <label class="block">
                    <span class="field-label">Fecha</span>
                    <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" required class="field-control">
                </label>
                <label class="block">
                    <span class="field-label">Método de pago</span>
                    <select name="metodo_pago" class="field-control">
                        <option value="">No especificado</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="otro">Otro</option>
                    </select>
                </label>
                <label class="block">
                    <span class="field-label">Referencia (opcional)</span>
                    <input name="referencia" value="{{ old('referencia') }}" maxlength="100" class="field-control">
                </label>
                <div class="md:col-span-2 xl:col-span-3">
                    <button class="button-primary px-5 py-3 transition">Guardar egreso</button>
                </div>
            </form>
        </section>

        <section class="panel overflow-hidden shadow-xl">
            <div class="border-b border-white/10 px-5 py-4 sm:px-7">
                <h2 class="font-bold text-white">Movimientos recientes</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Fecha</th>
                            <th class="px-5 py-3">Concepto</th>
                            <th class="px-5 py-3">Categoría</th>
                            <th class="px-5 py-3">Registró</th>
                            <th class="px-5 py-3 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($egresos as $egreso)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-300">{{ $egreso->fecha->format('d/m/Y') }}</td>
                                <td class="px-5 py-3 font-medium text-white">{{ $egreso->concepto }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ $egreso->categoria }}</td>
                                <td class="px-5 py-3 text-slate-400">{{ $egreso->usuario?->name ?? 'Cuenta eliminada' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-rose-200">${{ number_format((float) $egreso->monto, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">No hay egresos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-white/10 px-5 py-4 sm:px-7">{{ $egresos->links() }}</div>
        </section>
    </div>
@endsection
