<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion');
            $table->string('tipo')->nullable();
            $table->unsignedBigInteger('id_registro')->nullable();
            $table->json('metadatos')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->timestamps();
            $table->index(['tipo', 'id_registro']);
            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros');
    }
};
