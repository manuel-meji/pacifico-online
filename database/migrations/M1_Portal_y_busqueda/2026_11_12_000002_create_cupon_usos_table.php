<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Usos de los cupones (contrato 1.5.0, SC-11).
 * Una fila por pedido que usó el cupón: con ella M1 cuenta los usos por
 * cliente y hace que registrarUsoCupon sea idempotente por pedido.
 *
 * - id_cliente: cliente de M1 (tabla propia).
 * - id_pedido: referencia lógica al pedido maestro de M5 (sin llave foránea,
 *   frontera de datos del PC3); M1 nunca lee la tabla de pedidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupon_usos', function (Blueprint $table) {
            $table->id('id_cupon_uso');
            $table->unsignedBigInteger('id_cupon');
            $table->unsignedBigInteger('id_cliente');
            $table->unsignedBigInteger('id_pedido');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['id_cupon', 'id_pedido'], 'uq_cupon_usos_pedido');
            $table->index(['id_cupon', 'id_cliente'], 'ix_cupon_usos_cliente');
            $table->foreign('id_cupon', 'fk_cupon_usos_cupones')
                ->references('id_cupon')->on('cupones');
            $table->foreign('id_cliente', 'fk_cupon_usos_clientes')
                ->references('id_cliente')->on('clientes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupon_usos');
    }
};
