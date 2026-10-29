<div data-progreso-enrolamiento="{{ $clienteId ?? '' }}" class="border-t border-white/10 pt-5">
    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h3 class="text-sm font-bold text-slate-200">Enrolamiento de huella</h3>
            <p data-mensaje-enrolamiento class="mt-1 text-xs text-slate-400" role="status" aria-live="polite">
                {{ ($requiereGuardar ?? false) ? 'Se guardará el cliente antes de iniciar la captura.' : (($tieneHuella ?? false) ? 'Hay una huella registrada. Captura de nuevo para reemplazarla.' : 'Coloca y levanta el mismo dedo 4 veces.') }}
            </p>
        </div>
        <div class="flex shrink-0 items-center gap-2" role="group" aria-label="Progreso del enrolamiento">
            @for ($captura = 1; $captura <= 4; $captura++)
                <span data-paso-enrolamiento="{{ $captura }}" data-completed="false" class="size-3 rounded-full border border-white/15 bg-slate-700 transition-all duration-300 data-[completed=true]:border-teal-200 data-[completed=true]:bg-teal-300 data-[completed=true]:shadow-[0_0_14px_rgba(45,212,191,0.65)]"></span>
            @endfor
        </div>
    </div>
    <button
        type="{{ ($requiereGuardar ?? false) ? 'submit' : 'button' }}"
        @if ($requiereGuardar ?? false)
            name="registrar_huella_despues" value="1"
        @else
            data-enrolar-huella="{{ $clienteId ?? '' }}"
        @endif
        data-label-inicial="{{ ($tieneHuella ?? false) ? 'Actualizar huella' : 'Registrar huella' }}"
        class="mt-4 w-full rounded-xl border border-indigo-300/40 px-4 py-3 text-sm font-bold text-indigo-200 transition hover:border-indigo-200 hover:bg-indigo-300/10"
    >{{ ($requiereGuardar ?? false) ? 'Registrar huella' : (($tieneHuella ?? false) ? 'Actualizar huella' : 'Registrar huella') }}</button>
</div>
