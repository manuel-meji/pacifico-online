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
        Schema::create('items_carrito', function (Blueprint $table) {
            $table->id('id_item_carrito');$table->foreignId('id_carrito')->references('id_carrito')->on('pedidos_carritos')->onDelete('cascade');
            $table->foreignId('id_variacion')->constrained('variaciones');$table->integer('cantidad');
            $table->uuid('id_reserva')->nullable()->index();$table->timestamps();

            // Reglas
            $table->check('cantidad > 0', 'cantidad_positiva_carrito');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items_carrito');
    }
};
