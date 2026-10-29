<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reporte_ajustes_manuales', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('metodo_pago', 20);
            $table->string('concepto', 80);
            $table->decimal('monto', 10, 2);
            $table->unsignedInteger('orden')->default(0);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fecha', 'metodo_pago', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporte_ajustes_manuales');
    }
};
