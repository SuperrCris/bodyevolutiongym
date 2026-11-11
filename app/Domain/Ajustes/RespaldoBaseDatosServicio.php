<?php

namespace App\Domain\Ajustes;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class RespaldoBaseDatosServicio
{
    public function generar(): string
    {
        $conexion = DB::connection()->getConfig();

        if (($conexion['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException('La descarga de respaldos requiere una conexión PostgreSQL.');
        }

        $ejecutable = $this->buscarPgDump();

        if ($ejecutable === null) {
            throw new RuntimeException('No se encontró pg_dump en el servidor.');
        }

        $ruta = tempnam(sys_get_temp_dir(), 'body-evolution-gym-respaldo-');

        if ($ruta === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal del respaldo.');
        }

        $respaldoCompleto = false;

        try {
            $comando = [
                $ejecutable,
                '--no-owner',
                '--no-acl',
                '--no-password',
                '--format=plain',
                '--file='.$ruta,
            ];

            foreach ([
                'host' => '--host',
                'port' => '--port',
                'username' => '--username',
                'database' => '--dbname',
            ] as $llave => $opcion) {
                $valor = $conexion[$llave] ?? null;

                if ((! is_string($valor) && ! is_int($valor)) || trim((string) $valor) === '') {
                    continue;
                }

                $comando[] = $opcion.'='.(string) $valor;
            }

            $entorno = [
                'PGPASSWORD' => (string) ($conexion['password'] ?? ''),
            ];

            if (isset($conexion['sslmode']) && is_string($conexion['sslmode'])) {
                $entorno['PGSSLMODE'] = $conexion['sslmode'];
            }

            $proceso = new Process($comando, env: $entorno, timeout: 600);

            try {
                $proceso->mustRun();
            } catch (RuntimeException $exception) {
                throw new RuntimeException('Falló la creación del respaldo de PostgreSQL.', previous: $exception);
            }

            $tamaño = filesize($ruta);

            if ($tamaño === false || $tamaño === 0) {
                throw new RuntimeException('El respaldo de PostgreSQL se generó vacío.');
            }

            $respaldoCompleto = true;

            return $ruta;
        } finally {
            if (! $respaldoCompleto && is_file($ruta) && ! unlink($ruta)) {
                throw new RuntimeException('No se pudo eliminar el archivo temporal del respaldo.');
            }
        }
    }

    private function buscarPgDump(): ?string
    {
        $ejecutable = (new ExecutableFinder)->find('pg_dump');

        if ($ejecutable !== null) {
            return $ejecutable;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $directorioProgramas = getenv('ProgramFiles');

        if (! is_string($directorioProgramas) || $directorioProgramas === '') {
            return null;
        }

        $ejecutables = glob(
            $directorioProgramas.DIRECTORY_SEPARATOR.'PostgreSQL'.DIRECTORY_SEPARATOR.'*'
                .DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pg_dump.exe',
        );

        if ($ejecutables === false || $ejecutables === []) {
            return null;
        }

        sort($ejecutables, SORT_NATURAL);

        return $ejecutables[array_key_last($ejecutables)];
    }
}
