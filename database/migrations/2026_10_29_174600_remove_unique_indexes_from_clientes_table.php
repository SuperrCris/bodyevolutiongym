<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropUnique('customers_email_unique');
            $table->dropUnique('customers_document_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (
            DB::table('clientes')
                ->select('email')
                ->groupBy('email')
                ->havingRaw('COUNT(*) > 1')
                ->exists()
            || DB::table('clientes')
                ->select('documento')
                ->groupBy('documento')
                ->havingRaw('COUNT(*) > 1')
                ->exists()
        ) {
            throw new RuntimeException('No se pueden restaurar los índices únicos de clientes mientras existan correos o documentos duplicados.');
        }

        Schema::table('clientes', function (Blueprint $table): void {
            $table->unique('email', 'customers_email_unique');
            $table->unique('documento', 'customers_document_number_unique');
        });
    }
};
