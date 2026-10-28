<?php

namespace App\Http\Controllers;

use App\Domain\Auditoria\AuditoriaServicio;
use App\Domain\Impresion\TicketeraException;
use App\Domain\Impresion\TicketeraServicio;
use App\Domain\Membresias\MembresiasServicio;
use App\Models\Cliente;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\TipoMembresia;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientesController extends Controller
{
    public function index(Request $request): View
    {
        $clientes = Cliente::query()
            ->with([
                'membresias' => fn ($query) => $query->latest('finalizacion')->with('plan'),
                'credencialesBiometricas' => fn ($query) => $query->where('estado', true),
            ])
            ->when($request->filled('buscar'), function ($query) use ($request): void {
                $this->applyClientSearch($query, $request->string('buscar')->toString());
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $clienteParaEnrolar = null;
        $clienteIdParaEnrolar = $request->session()->pull('cliente_para_enrolar');

        if ($clienteIdParaEnrolar !== null) {
            $clienteParaEnrolar = Cliente::query()
                ->with(['credencialesBiometricas' => fn ($query) => $query->where('estado', true)])
                ->findOrFail($clienteIdParaEnrolar);
        }

        return view('clientes.index', [
            'clientes' => $clientes,
            'clienteParaEnrolar' => $clienteParaEnrolar,
            'planes' => TipoMembresia::query()->where('activo', true)->orderBy('duracion_dias')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'registrar_huella_despues' => ['sometimes', 'boolean'],
        ]);
        $registrarHuellaDespues = $request->boolean('registrar_huella_despues');
        $data = $this->validatedData($request);
        $foto = $request->file('foto');

        if ($foto !== null) {
            $data['foto'] = $this->storePhoto($foto);
        } else {
            unset($data['foto']);
        }

        $cliente = Cliente::create($data);
        $redirect = to_route('clientes.index')->with('success', 'Cliente creado correctamente.');

        if ($registrarHuellaDespues) {
            $redirect->with('cliente_para_enrolar', $cliente->id);
        }

        return $redirect;
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        $data = $this->validatedData($request, $cliente);
        $fotoAnterior = $cliente->foto;
        $foto = $request->file('foto');

        if ($foto !== null) {
            $data['foto'] = $this->storePhoto($foto);
        } else {
            unset($data['foto']);
        }

        $cliente->update($data);

        if ($foto !== null && $fotoAnterior !== null) {
            Storage::disk('public')->delete($fotoAnterior);
        }

        return to_route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    public function renovar(
        Request $request,
        Cliente $cliente,
        MembresiasServicio $servicio,
        TicketeraServicio $ticketera,
    ): RedirectResponse {
        $data = $request->validate([
            'tipo_membresia_id' => [
                'required',
                'integer',
                Rule::exists('tipo_membresias', 'id')->where('activo', true),
            ],
            'metodo_pago' => ['required', 'string', 'in:efectivo,tarjeta,transferencia'],
            'referencia' => ['nullable', 'string', 'max:100', 'unique:pagos,referencia'],
            'imprimir' => ['sometimes', 'in:true,1'],
        ]);

        $metodos = [
            'efectivo',
            'tarjeta',
            'transferencia',
        ];

        $membresia = $servicio->renovar(
            $cliente,
            TipoMembresia::findOrFail($data['tipo_membresia_id']),
            $data['metodo_pago'],
            $request->user(),
            $data['referencia'] ?? null,
        );

        if ($request->boolean('imprimir')) {
            $pago = $membresia->pagos()->firstOrFail();

            return $this->enviarRecibo($pago, $ticketera);
        }

        return to_route('clientes.index')->with('success', 'Membresía renovada correctamente.');
    }

    public function actualizarVigencia(
        Request $request,
        Membresia $membresia,
        AuditoriaServicio $auditoria,
    ): RedirectResponse {
        $administrador = $request->user();

        if (! ($administrador instanceof Usuario) || ! $administrador->esAdministrador()) {
            abort(403);
        }

        $data = $request->validate([
            'finalizacion' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now()->toDateString()],
        ], [
            'finalizacion.after_or_equal' => 'La vigencia no puede ser anterior al día actual.',
        ]);
        $fechaNueva = $data['finalizacion'];

        $actualizado = DB::transaction(function () use (
            $membresia,
            $administrador,
            $auditoria,
            $fechaNueva,
        ): bool {
            $membresiaBloqueada = Membresia::query()
                ->lockForUpdate()
                ->findOrFail($membresia->getKey());
            $fechaAnterior = $membresiaBloqueada->finalizacion->toDateString();

            if ($fechaAnterior === $fechaNueva) {
                return false;
            }

            $membresiaBloqueada->finalizacion = $fechaNueva;
            $membresiaBloqueada->save();

            $auditoria->registrarCambioFinalizacionMembresia(
                $membresiaBloqueada,
                $fechaAnterior,
                $fechaNueva,
                $administrador,
            );

            return true;
        });

        return to_route('clientes.index')->with(
            'success',
            $actualizado
                ? 'Vigencia de membresía actualizada y registrada en auditoría.'
                : 'La membresía ya tenía esa fecha de vigencia; no se registró ningún cambio.',
        );
    }

    public function printReceipt(Pago $pago, TicketeraServicio $ticketera): RedirectResponse
    {
        return $this->enviarRecibo($pago, $ticketera);
    }

    public function receipt(Pago $pago): View
    {
        $pago->load(['cliente', 'membresia.plan', 'usuario']);

        return view('pagos.recibo', [
            'pago' => $pago,
            'metodoPago' => match ($pago->metodo_pago) {
                'efectivo' => 'Efectivo',
                'tarjeta' => 'Tarjeta',
                'transferencia' => 'Transferencia',
                default => $pago->metodo_pago,
            },
        ]);
    }

    private function enviarRecibo(Pago $pago, TicketeraServicio $ticketera): RedirectResponse
    {
        try {
            $impresora = $ticketera->imprimir($pago);
        } catch (TicketeraException $exception) {
            return to_route('pagos.recibo', $pago)->with('error', $exception->getMessage());
        }

        return to_route('clientes.index')
            ->with('success', "Recibo enviado a la impresora predeterminada: {$impresora}.");
    }

    public function resumen(): JsonResponse
    {
        return response()->json([
            'clientes' => Cliente::query()->count(),
            'membresias_activas' => Cliente::query()
                ->whereHas('membresias', fn ($query) => $query
                    ->where('estado', 'activa')
                    ->whereDate('finalizacion', '>=', now()->toDateString()))
                ->count(),
            'ultimos_clientes' => Cliente::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'nombre', 'apellido', 'created_at']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Cliente $cliente = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('clientes', 'email')->ignore($cliente?->id),
            ],
            'telefono' => ['nullable', 'string', 'max:30'],
            'documento' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('clientes', 'documento')->ignore($cliente?->id),
            ],
            'fecha_nacimiento' => ['nullable', 'date'],
            'foto' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
                'dimensions:max_width=2000,max_height=2000',
            ],
        ]);
    }

    private function storePhoto(UploadedFile $foto): string
    {
        $path = $foto->store('clientes', 'public');

        if ($path === false) {
            throw new \RuntimeException('No fue posible guardar la foto del cliente.');
        }

        return $path;
    }

    private function applyClientSearch(Builder $query, string $search): void
    {
        $term = Str::lower(trim($search));
        $likeTerm = "%{$term}%";

        $query->where(function (Builder $query) use ($term, $likeTerm): void {
            $query->whereRaw('LOWER(nombre) LIKE ?', [$likeTerm])
                ->orWhereRaw('LOWER(apellido) LIKE ?', [$likeTerm])
                ->orWhereRaw('LOWER(documento) LIKE ?', [$likeTerm]);

            if (ctype_digit($term)) {
                $query->orWhere('id', (int) $term);
            }

            if (DB::connection()->getDriverName() !== 'pgsql' || Str::length($term) < 3) {
                return;
            }

            $threshold = $this->similarityThreshold($term);
            $query->orWhereRaw('word_similarity(?, LOWER(nombre)) >= ?', [$term, $threshold])
                ->orWhereRaw('word_similarity(?, LOWER(apellido)) >= ?', [$term, $threshold])
                ->orWhereRaw('word_similarity(?, LOWER(documento)) >= ?', [$term, $threshold]);
        });
    }

    private function similarityThreshold(string $term): float
    {
        return match (true) {
            Str::length($term) <= 4 => 0.25,
            Str::contains($term, ' ') => 0.4,
            default => 0.45,
        };
    }
}
