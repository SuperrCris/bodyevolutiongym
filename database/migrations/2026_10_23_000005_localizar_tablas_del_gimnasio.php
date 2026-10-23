<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('customers', 'clientes');
        Schema::table('clientes', function (Blueprint $table): void {
            $table->renameColumn('first_name', 'nombre');
            $table->renameColumn('last_name', 'apellido');
            $table->renameColumn('phone', 'telefono');
            $table->renameColumn('document_number', 'documento');
            $table->renameColumn('birth_date', 'fecha_nacimiento');
            $table->renameColumn('active', 'estado');
        });

        Schema::rename('membership_plans', 'tipo_membresias');
        Schema::table('tipo_membresias', function (Blueprint $table): void {
            $table->renameColumn('name', 'nombre');
            $table->renameColumn('duration_days', 'duracion_dias');
            $table->renameColumn('price', 'precio');
            $table->renameColumn('active', 'activo');
        });

        Schema::rename('memberships', 'membresias');
        Schema::table('membresias', function (Blueprint $table): void {
            $table->renameColumn('customer_id', 'id_cliente');
            $table->renameColumn('membership_plan_id', 'tipo_membresia_id');
            $table->renameColumn('starts_at', 'comienzo');
            $table->renameColumn('ends_at', 'finalizacion');
            $table->renameColumn('status', 'estado');
            $table->string('estado')->default('activa')->change();
        });
        DB::table('membresias')->where('estado', 'active')->update(['estado' => 'activa']);

        Schema::rename('payments', 'pagos');
        Schema::table('pagos', function (Blueprint $table): void {
            $table->renameColumn('customer_id', 'id_cliente');
            $table->renameColumn('membership_id', 'membresia_id');
            $table->renameColumn('user_id', 'usuario_id');
            $table->renameColumn('amount', 'monto');
            $table->renameColumn('payment_method', 'metodo_pago');
            $table->renameColumn('reference', 'referencia');
        });

        Schema::rename('biometric_credentials', 'credencial_biometricas');
        Schema::table('credencial_biometricas', function (Blueprint $table): void {
            $table->renameColumn('customer_id', 'id_cliente');
            $table->renameColumn('provider', 'proveedor');
            $table->renameColumn('external_id', 'id_externo');
            $table->renameColumn('template', 'plantilla');
            $table->renameColumn('active', 'estado');
        });

        Schema::rename('terminals', 'terminales');
        Schema::table('terminales', function (Blueprint $table): void {
            $table->renameColumn('name', 'nombre');
            $table->renameColumn('last_seen_at', 'ultima_vez');
        });
    }

    public function down(): void
    {
        Schema::table('terminales', function (Blueprint $table): void {
            $table->renameColumn('nombre', 'name');
            $table->renameColumn('ultima_vez', 'last_seen_at');
        });
        Schema::rename('terminales', 'terminals');

        Schema::table('credencial_biometricas', function (Blueprint $table): void {
            $table->renameColumn('id_cliente', 'customer_id');
            $table->renameColumn('proveedor', 'provider');
            $table->renameColumn('id_externo', 'external_id');
            $table->renameColumn('plantilla', 'template');
            $table->renameColumn('estado', 'active');
        });
        Schema::rename('credencial_biometricas', 'biometric_credentials');

        Schema::table('pagos', function (Blueprint $table): void {
            $table->renameColumn('id_cliente', 'customer_id');
            $table->renameColumn('membresia_id', 'membership_id');
            $table->renameColumn('usuario_id', 'user_id');
            $table->renameColumn('monto', 'amount');
            $table->renameColumn('metodo_pago', 'payment_method');
            $table->renameColumn('referencia', 'reference');
        });
        Schema::rename('pagos', 'payments');

        Schema::table('membresias', function (Blueprint $table): void {
            $table->renameColumn('id_cliente', 'customer_id');
            $table->renameColumn('tipo_membresia_id', 'membership_plan_id');
            $table->renameColumn('comienzo', 'starts_at');
            $table->renameColumn('finalizacion', 'ends_at');
            $table->renameColumn('estado', 'status');
            $table->string('status')->default('active')->change();
        });
        DB::table('membresias')->where('status', 'activa')->update(['status' => 'active']);
        Schema::rename('membresias', 'memberships');

        Schema::table('tipo_membresias', function (Blueprint $table): void {
            $table->renameColumn('nombre', 'name');
            $table->renameColumn('duracion_dias', 'duration_days');
            $table->renameColumn('precio', 'price');
            $table->renameColumn('activo', 'active');
        });
        Schema::rename('tipo_membresias', 'membership_plans');

        Schema::table('clientes', function (Blueprint $table): void {
            $table->renameColumn('nombre', 'first_name');
            $table->renameColumn('apellido', 'last_name');
            $table->renameColumn('telefono', 'phone');
            $table->renameColumn('documento', 'document_number');
            $table->renameColumn('fecha_nacimiento', 'birth_date');
            $table->renameColumn('estado', 'active');
        });
        Schema::rename('clientes', 'customers');
    }
};
