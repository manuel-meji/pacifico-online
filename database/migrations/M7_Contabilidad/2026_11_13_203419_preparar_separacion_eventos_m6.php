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
        Schema::create('pagos_contables_m6', function (Blueprint $tabla): void {
            $tabla->unsignedBigInteger('id_pago')->primary();
            $tabla->unsignedBigInteger('id_pedido');
            $tabla->unsignedBigInteger('monto_centimos');
            $tabla->timestamp('fecha');
            $tabla->string('huella', 64);
            $tabla->unsignedBigInteger('id_asiento_plataforma')->nullable()->unique();
            $tabla->timestamps();
            $tabla->foreign('id_asiento_plataforma')->references('id_asiento')->on('asientos_plataforma_m7')->restrictOnDelete();
        });
        Schema::create('bandeja_eventos_m6', function (Blueprint $tabla): void {
            $tabla->uuid('evento_id')->primary();
            $tabla->string('tipo_evento', 80);
            $tabla->unsignedBigInteger('id_pago');
            $tabla->unsignedBigInteger('id_comercio')->nullable();
            $tabla->json('payload');
            $tabla->string('huella', 64);
            $tabla->string('estado', 30)->default('PENDIENTE');
            $tabla->unsignedInteger('intentos')->default(0);
            $tabla->text('ultimo_error')->nullable();
            $tabla->timestamp('fecha_procesamiento')->nullable();
            $tabla->timestamps();
            $tabla->index(['estado', 'id_pago']);
        });
        Schema::create('cuadres_pagos_m6', function (Blueprint $tabla): void {
            $tabla->unsignedBigInteger('id_pago')->primary();
            $tabla->unsignedBigInteger('facturado_centimos')->nullable();
            $tabla->unsignedBigInteger('saldo_pendiente_centimos')->nullable();
            $tabla->unsignedBigInteger('exceso_centimos')->nullable();
            $tabla->string('estado', 30);
            $tabla->text('ultimo_error')->nullable();
            $tabla->timestamp('fecha_alerta')->nullable();
            $tabla->timestamps();
            $tabla->foreign('id_pago')->references('id_pago')->on('pagos_contables_m6')->restrictOnDelete();
        });
        Schema::table('registros_financieros_m6', function (Blueprint $tabla): void {
            $tabla->unsignedBigInteger('id_asiento_plataforma')->nullable()->unique();
            $tabla->foreign('id_asiento_plataforma')->references('id_asiento')->on('asientos_plataforma_m7')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registros_financieros_m6', function (Blueprint $tabla): void {
            $tabla->dropForeign(['id_asiento_plataforma']);
            $tabla->dropUnique(['id_asiento_plataforma']);
            $tabla->dropColumn('id_asiento_plataforma');
        });
        Schema::dropIfExists('cuadres_pagos_m6');
        Schema::dropIfExists('bandeja_eventos_m6');
        Schema::dropIfExists('pagos_contables_m6');
    }
};
