<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Solicitudes de un comercio para destacar uno de sus
 * productos (contrato 1.4.0, SC-08). La administración las aprueba o rechaza.
 *
 * monto es la tarifa del periodo vigente al solicitar: es la que el comercio
 * vio y la que se cobra al aprobar. Un producto tiene como máximo una
 * solicitud pendiente a la vez (índice único parcial).
 *
 * Llaves foráneas hacia comercios (M2), productos (M3) y usuarios (núcleo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_destacado', function (Blueprint $table) {
            $table->id('id_solicitud_destacado');
            $table->unsignedBigInteger('id_comercio');
            $table->unsignedBigInteger('id_producto');
            $table->smallInteger('periodo_dias');
            $table->decimal('monto', 12, 2);
            $table->char('moneda', 3)->default('CRC');
            $table->string('estado', 20)->default('pendiente');
            $table->string('motivo_rechazo', 500)->nullable();
            $table->unsignedBigInteger('id_usuario_solicita');
            $table->unsignedBigInteger('id_usuario_resuelve')->nullable();
            $table->timestamp('fecha_resolucion')->nullable();
            $table->timestamps();

            $table->index(['id_comercio', 'estado'], 'ix_solicitudes_destacado_comercio_estado');
            $table->foreign('id_comercio', 'fk_solicitudes_destacado_comercios')
                ->references('id_comercio')->on('comercios');
            $table->foreign('id_producto', 'fk_solicitudes_destacado_productos')
                ->references('id_producto')->on('productos');
            $table->foreign('id_usuario_solicita', 'fk_solicitudes_destacado_usuario_solicita')
                ->references('id_usuario')->on('usuarios');
            $table->foreign('id_usuario_resuelve', 'fk_solicitudes_destacado_usuario_resuelve')
                ->references('id_usuario')->on('usuarios');
        });

        DB::statement("ALTER TABLE solicitudes_destacado ADD CONSTRAINT chk_solicitudes_destacado_estado
                       CHECK (estado IN ('pendiente', 'aprobada', 'rechazada'))");
        DB::statement('ALTER TABLE solicitudes_destacado ADD CONSTRAINT chk_solicitudes_destacado_periodo
                       CHECK (periodo_dias IN (7, 15, 30))');
        DB::statement('ALTER TABLE solicitudes_destacado ADD CONSTRAINT chk_solicitudes_destacado_monto
                       CHECK (monto >= 0)');
        DB::statement("ALTER TABLE solicitudes_destacado ADD CONSTRAINT chk_solicitudes_destacado_rechazo
                       CHECK (estado <> 'rechazada' OR motivo_rechazo IS NOT NULL)");
        DB::statement("CREATE UNIQUE INDEX uq_solicitudes_destacado_pendiente
                       ON solicitudes_destacado (id_producto) WHERE estado = 'pendiente'");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_destacado');
    }
};
