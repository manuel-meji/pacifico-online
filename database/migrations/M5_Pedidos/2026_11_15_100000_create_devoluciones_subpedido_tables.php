<?php

// M5 — Carrito y Pedidos (SG5)
// RF-019 / RF-020 / RF-021: devoluciones de subpedidos entregados con revisión del comercio.
// Tablas nuevas y autocontenidas: no modifica ninguna tabla existente.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones_subpedido', function (Blueprint $table) {
            $table->id('id_devolucion');
            $table->foreignId('id_pedido')->references('id_pedido')->on('pedidos_maestros')->onDelete('cascade');
            $table->foreignId('id_subpedido')->references('id_subpedido')->on('pedidos_subpedidos')->onDelete('cascade');
            $table->unsignedBigInteger('id_comercio')->index();
            $table->unsignedBigInteger('id_cliente')->index();
            $table->string('tipo', 10)->comment('total | parcial');
            $table->string('estado', 15)->default('solicitada')->index()->comment('solicitada | aprobada | rechazada');
            $table->text('motivo');
            $table->text('motivo_rechazo')->nullable();
            $table->decimal('monto_solicitado', 12, 2);
            $table->decimal('monto_reembolsado', 12, 2)->nullable();
            $table->unsignedBigInteger('id_reembolso_m6')->nullable()->comment('Referencia lógica a M6');
            $table->string('estado_reembolso', 20)->nullable()->comment('pendiente | procesado | error');
            $table->text('error_reembolso')->nullable();
            $table->unsignedBigInteger('id_usuario_revision')->nullable();
            $table->timestamp('fecha_solicitud');
            $table->timestamp('fecha_revision')->nullable();
            $table->timestamps();

            $table->index(['id_subpedido', 'estado'], 'idx_devoluciones_subpedido_estado');
        });

        Schema::create('devoluciones_lineas', function (Blueprint $table) {
            $table->id('id_devolucion_linea');
            $table->foreignId('id_devolucion')->references('id_devolucion')->on('devoluciones_subpedido')->onDelete('cascade');
            $table->unsignedBigInteger('id_linea')->index()->comment('pedidos_lineas.id_linea (M5)');
            $table->unsignedBigInteger('id_variacion');
            $table->uuid('id_reserva')->nullable();
            $table->unsignedInteger('cantidad');
            $table->decimal('monto', 12, 2);
            $table->timestamps();
        });

        Schema::create('devoluciones_evidencias', function (Blueprint $table) {
            $table->id('id_evidencia');
            $table->foreignId('id_devolucion')->references('id_devolucion')->on('devoluciones_subpedido')->onDelete('cascade');
            $table->string('disco', 30);
            $table->string('ruta', 255);
            $table->string('nombre_original', 255);
            $table->string('mime', 100);
            $table->unsignedBigInteger('tamano');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devoluciones_evidencias');
        Schema::dropIfExists('devoluciones_lineas');
        Schema::dropIfExists('devoluciones_subpedido');
    }
};
