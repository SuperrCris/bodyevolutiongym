<?php

namespace App\Domain\Ajustes;

use Illuminate\Support\Facades\DB;

class AjustesTicketServicio
{
    private const ENCABEZADO_PREDETERMINADO = "BODY EVOLUTION GYM\nJOSE ROSARIO GUTIERREZ MELENDEZ\nGUMR790402B66\n6421095410";

    private const PIE_PREDETERMINADO = '¡Gracias por tu preferencia!';

    /**
     * @return array{encabezado: string, pie: string}
     */
    public function obtenerConfiguracion(): array
    {
        $valores = DB::table('ajustes_sistema')
            ->whereIn('clave', ['ticket_encabezado', 'ticket_pie'])
            ->pluck('valor', 'clave');

        return [
            'encabezado' => $valores->get('ticket_encabezado', self::ENCABEZADO_PREDETERMINADO),
            'pie' => $valores->get('ticket_pie', self::PIE_PREDETERMINADO),
        ];
    }

    public function guardarConfiguracion(string $encabezado, string $pie): void
    {
        $ahora = now();

        DB::table('ajustes_sistema')->upsert([
            [
                'clave' => 'ticket_encabezado',
                'valor' => $encabezado,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'clave' => 'ticket_pie',
                'valor' => $pie,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
        ], ['clave'], ['valor', 'updated_at']);
    }
}
