<?php

/*
 * Benchmark de identificación biométrica 1:N. No se ejecuta en la suite normal.
 *
 * Simulado (mide consulta + descifrado + envío por lotes de Laravel; la latencia simulada es por comparación del SDK):
 *   $env:BIOMETRIA_BENCHMARK=1; php artisan test --compact tests/Feature/RendimientoAutenticacionBiometricaTest.php
 *   Opcionales: BIOMETRIA_BENCH_TAMANOS="10,100,500,1000"  BIOMETRIA_BENCH_LATENCIA_MS=5  BIOMETRIA_BENCH_LIMITE_MS=1500
 *
 * Real (puente Java encendido con el lector; mide el tiempo de comparación del SDK). Los datos se leen desde archivos
 * porque son demasiado largos para una variable de entorno (guárdelos fuera de git, p. ej. en storage\app\bench):
 *   $env:BIOMETRIA_BENCH_REAL=1
 *   $env:BIOMETRIA_BENCH_MUESTRA_ARCHIVO="<ruta>"          (campo "sample" de POST http://127.0.0.1:8765/capturar)
 *   $env:BIOMETRIA_BENCH_PLANTILLA_AJENA_ARCHIVO="<ruta>"  (plantilla base64 que NO coincide con la muestra)
 *   $env:BIOMETRIA_BENCH_PLANTILLA_PROPIA_ARCHIVO="<ruta>" (opcional: la que SÍ coincide; se coloca al final)
 *   También se acepta el valor directo en BIOMETRIA_BENCH_MUESTRA, BIOMETRIA_BENCH_PLANTILLA_AJENA y _PROPIA.
 *
 * Usa la base de datos de pruebas (SQLite en memoria); no toca la base real.
 */

use App\Domain\Biometria\VerificadorBiometrico;
use App\Models\CredencialBiometrica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function tamanosBenchmarkBiometrico(): array
{
    $tamanos = (string) (getenv('BIOMETRIA_BENCH_TAMANOS') ?: '10,100,500,1000');

    return array_map('intval', array_filter(explode(',', $tamanos)));
}

/**
 * Lee un dato del benchmark desde la variable indicada o, si existe, desde el archivo de {NOMBRE}_ARCHIVO.
 */
function entradaBenchmarkBiometrico(string $nombre): string
{
    $archivo = getenv($nombre.'_ARCHIVO');

    if ($archivo !== false && $archivo !== '') {
        return is_file($archivo) ? trim((string) file_get_contents($archivo)) : '';
    }

    return trim((string) getenv($nombre));
}

/**
 * @param  array<int, string>  $plantillas
 */
function sembrarCredencialesSinteticas(array $plantillas): void
{
    $ahora = now();
    $filasClientes = [];

    foreach (array_keys($plantillas) as $indice) {
        $filasClientes[] = [
            'nombre' => 'Cliente',
            'apellido' => 'Sintético '.$indice,
            'email' => "benchmark{$indice}@example.com",
            'estado' => true,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
    }

    foreach (array_chunk($filasClientes, 100) as $lote) {
        DB::table('clientes')->insert($lote);
    }

    $idsClientes = DB::table('clientes')->orderBy('id')->pluck('id')->all();
    $filasCredenciales = [];

    foreach ($plantillas as $indice => $plantilla) {
        $filasCredenciales[] = [
            'id_cliente' => $idsClientes[$indice],
            'proveedor' => 'digitalpersona',
            'id_externo' => Str::uuid()->toString(),
            'plantilla' => Crypt::encryptString($plantilla),
            'estado' => true,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
    }

    foreach (array_chunk($filasCredenciales, 100) as $lote) {
        DB::table('credencial_biometricas')->insert($lote);
    }
}

/**
 * @return array{consulta: float, descifrado: float, total: float, resultado: array<string, mixed>}
 */
function medirIdentificacionBiometrica(string $muestra, int $cantidad, string $escenario): array
{
    CredencialBiometrica::query()->where('estado', true)->limit(1)->get();

    $inicio = hrtime(true);
    $credenciales = CredencialBiometrica::query()->where('estado', true)->get();
    $consulta = (hrtime(true) - $inicio) / 1e6;

    $inicio = hrtime(true);
    foreach ($credenciales as $credencial) {
        $credencial->plantilla;
    }
    $descifrado = (hrtime(true) - $inicio) / 1e6;

    $inicio = hrtime(true);
    $resultado = app(VerificadorBiometrico::class)->verificar($muestra, 'benchmark');
    $total = (hrtime(true) - $inicio) / 1e6;

    fwrite(STDERR, sprintf(
        "\n[biometria-bench] N=%-5d %-16s consulta=%8.1f ms  descifrado=%8.1f ms  total=%9.1f ms  puente/HTTP=%9.1f ms  (%.2f ms/credencial)",
        $cantidad,
        $escenario,
        $consulta,
        $descifrado,
        $total,
        max(0, $total - $consulta - $descifrado),
        $total / max(1, $cantidad),
    ));

    return compact('consulta', 'descifrado', 'total', 'resultado');
}

function verificarLimiteBenchmarkBiometrico(float $total): void
{
    $limite = getenv('BIOMETRIA_BENCH_LIMITE_MS');

    if ($limite !== false && $limite !== '') {
        expect($total)->toBeLessThanOrEqual((float) $limite);
    }
}

test('mide el tiempo de identificación con puente simulado según cantidad de huellas y posición de la coincidencia', function (int $cantidad, string $escenario) {
    $objetivo = base64_encode(random_bytes(1600));
    $relleno = base64_encode(random_bytes(1600));
    $plantillas = array_fill(0, $cantidad, $relleno);

    if ($escenario === 'primera') {
        $plantillas[0] = $objetivo;
    } elseif ($escenario === 'ultima') {
        $plantillas[$cantidad - 1] = $objetivo;
    }

    sembrarCredencialesSinteticas($plantillas);

    $llamadas = 0;
    $latenciaMicrosegundos = (int) ((float) (getenv('BIOMETRIA_BENCH_LATENCIA_MS') ?: 0) * 1000);
    $coincidir = coincidirConCandidatos($objetivo);

    Http::preventStrayRequests();
    Http::fake([
        rtrim((string) config('biometria.url'), '/').'/identificar' => function (Request $request) use ($coincidir, &$llamadas, $latenciaMicrosegundos) {
            $llamadas++;

            if ($latenciaMicrosegundos > 0) {
                usleep($latenciaMicrosegundos * count($request['candidatos']));
            }

            return $coincidir($request);
        },
    ]);

    $medicion = medirIdentificacionBiometrica(base64_encode('muestra-benchmark'), $cantidad, $escenario);

    $lotes = (int) ceil($cantidad / max(1, (int) config('biometria.lote_identificacion')));
    $esperadas = $escenario === 'primera' ? 1 : $lotes;

    expect($llamadas)->toBe($esperadas)
        ->and($medicion['resultado']['matched'])->toBe($escenario !== 'sin coincidencia');
    verificarLimiteBenchmarkBiometrico($medicion['total']);
})->with(fn () => tamanosBenchmarkBiometrico())
    ->with(['primera', 'ultima', 'sin coincidencia'])
    ->skip(! getenv('BIOMETRIA_BENCHMARK'), 'Defina BIOMETRIA_BENCHMARK=1 para ejecutar el benchmark.');

test('mide el tiempo de identificación contra el puente biométrico real', function (int $cantidad) {
    $muestra = entradaBenchmarkBiometrico('BIOMETRIA_BENCH_MUESTRA');
    $ajena = entradaBenchmarkBiometrico('BIOMETRIA_BENCH_PLANTILLA_AJENA');
    $propia = entradaBenchmarkBiometrico('BIOMETRIA_BENCH_PLANTILLA_PROPIA');

    $plantillas = array_fill(0, $cantidad, $ajena);

    if ($propia !== '') {
        $plantillas[$cantidad - 1] = $propia;
    }

    sembrarCredencialesSinteticas($plantillas);

    $escenario = $propia !== '' ? 'ultima' : 'sin coincidencia';
    $medicion = medirIdentificacionBiometrica($muestra, $cantidad, $escenario);

    expect($medicion['resultado']['matched'])->toBe($propia !== '');
    verificarLimiteBenchmarkBiometrico($medicion['total']);
})->with(fn () => tamanosBenchmarkBiometrico())
    ->skip(
        ! getenv('BIOMETRIA_BENCH_REAL')
            || entradaBenchmarkBiometrico('BIOMETRIA_BENCH_MUESTRA') === ''
            || entradaBenchmarkBiometrico('BIOMETRIA_BENCH_PLANTILLA_AJENA') === '',
        'Defina BIOMETRIA_BENCH_REAL, BIOMETRIA_BENCH_MUESTRA_ARCHIVO y BIOMETRIA_BENCH_PLANTILLA_AJENA_ARCHIVO.',
    );
