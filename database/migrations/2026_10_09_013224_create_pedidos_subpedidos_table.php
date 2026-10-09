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
        Schema::create('pedidos_subpedidos', function (Blueprint $table) {
            $table->id('id_subpedido');$table->foreignId('id_pedido')->references('id_pedido')->on('pedidos_maestros')->onDelete('cascade');
            $table->foreignId('id_comercio')->constrained('comercios');$table->decimal('subtotal', 10, 2);
            $table->decimal('costo_envio', 10, 2);$table->decimal('impuesto', 10, 2);
            $table->string('estado_subpedido', 50);$table->timestamps();

            // Reglas
            $table->check('subtotal >= 0', 'subtotal_positivo');$table->check('costo_envio >= 0', 'costo_envio_positivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos_subpedidos');
    }
};
