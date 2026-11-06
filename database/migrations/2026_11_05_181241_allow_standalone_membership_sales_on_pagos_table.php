<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        Schema::table('pagos', function (Blueprint $table) use ($sqlite): void {
            if (! $sqlite) {
                $table->dropForeign('payments_customer_id_foreign');
            }

            $table->foreignId('id_cliente')->nullable()->change();

            if (! $sqlite) {
                $table->foreign('id_cliente', 'payments_customer_id_foreign')->references('id')->on('clientes');
            }

            $table->foreignId('tipo_membresia_id')
                ->nullable()
                ->constrained('tipo_membresias')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('pagos')->whereNull('id_cliente')->exists()) {
            throw new RuntimeException(
                'No se puede revertir la venta de membresías sin cliente mientras existan pagos independientes.',
            );
        }

        $sqlite = DB::getDriverName() === 'sqlite';

        Schema::table('pagos', function (Blueprint $table) use ($sqlite): void {
            if (! $sqlite) {
                $table->dropForeign(['tipo_membresia_id']);
            }

            $table->dropColumn('tipo_membresia_id');

            if (! $sqlite) {
                $table->dropForeign('payments_customer_id_foreign');
            }

            $table->foreignId('id_cliente')->nullable(false)->change();

            if (! $sqlite) {
                $table->foreign('id_cliente', 'payments_customer_id_foreign')->references('id')->on('clientes');
            }
        });
    }
};
