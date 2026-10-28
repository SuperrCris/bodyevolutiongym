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
        Schema::create('credenciales_biometricas_usuarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('proveedor', 50)->default('digitalpersona');
            $table->uuid('id_externo')->unique();
            $table->longText('plantilla');
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->index(['usuario_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credenciales_biometricas_usuarios');
    }
};
