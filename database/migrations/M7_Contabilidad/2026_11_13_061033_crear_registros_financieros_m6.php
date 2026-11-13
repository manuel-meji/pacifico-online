<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_financieros_m6', function (Blueprint $table) {
            $table->id('id_registro');
            $table->string('referencia', 120)->unique();
            $table->string('tipo', 20);
            $table->unsignedBigInteger('id_operacion');
            $table->unsignedBigInteger('id_pago');
            $table->unsignedBigInteger('id_pedido');
            $table->unsignedBigInteger('id_subpedido');
            $table->unsignedBigInteger('id_comercio');
            $table->unsignedBigInteger('id_asiento')->unique();
            $table->unsignedBigInteger('subtotal_centimos');
            $table->unsignedBigInteger('impuesto_centimos');
            $table->unsignedBigInteger('envio_centimos');
            $table->string('huella', 64);
            $table->timestamps();

            $table->unique(['tipo', 'id_operacion']);
            $table->index(['id_comercio', 'id_subpedido', 'tipo']);
            $table->foreign('id_asiento')->references('id_asiento')->on('asientos_contables')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_financieros_m6');
    }
};
