<?php

// M2 — Micrositios y administración de comercios (SG2)
// RF-021: reversión (total o parcial) de la comisión de un subpedido por una devolución aprobada.
// Tabla nueva: la comisión original (comisiones_subpedido) no se modifica; la reversión queda
// como un registro aparte, único por devolución (idempotencia).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comisiones_reversion', function (Blueprint $table) {
            $table->id('id_comision_reversion');
            $table->unsignedBigInteger('id_comision_subpedido')->index();
            $table->unsignedBigInteger('id_subpedido')->index()->comment('Referencia lógica a pedidos_subpedidos (M5)');
            $table->unsignedBigInteger('id_comercio');
            $table->unsignedBigInteger('id_devolucion')->unique('uq_comision_reversion_devolucion')->comment('Referencia lógica a M5');
            $table->uuid('id_evento')->unique('uq_comision_reversion_evento');
            $table->decimal('subtotal_devuelto', 12, 2);
            $table->decimal('monto_revertido', 12, 2);
            $table->char('moneda', 3)->default('CRC');
            $table->timestamps();

            $table->foreign('id_comision_subpedido', 'fk_comisiones_reversion_comision')
                ->references('id_comision_subpedido')->on('comisiones_subpedido')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comisiones_reversion');
    }
};
