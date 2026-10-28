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
        Schema::create('egresos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('concepto', 160);
            $table->string('categoria', 80);
            $table->decimal('monto', 10, 2);
            $table->date('fecha');
            $table->string('metodo_pago', 30)->nullable();
            $table->string('referencia', 100)->nullable();
            $table->timestamps();

            $table->index(['fecha', 'categoria']);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->index('created_at', 'pagos_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndex('pagos_created_at_index');
        });

        Schema::dropIfExists('egresos');
    }
};
