<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items_carrito', function (Blueprint $table) {
            $table->id('id_item_carrito');
            $table->foreignId('id_carrito')->references('id_carrito')->on('pedidos_carritos')->onDelete('cascade');
            $table->unsignedBigInteger('id_variacion');
            $table->integer('cantidad');
            $table->uuid('id_reserva')->nullable()->index();
            $table->timestamps();

            $table->unique(['id_carrito', 'id_variacion'], 'items_carrito_unique');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE items_carrito ADD CONSTRAINT cantidad_positiva_carrito CHECK (cantidad > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('items_carrito');
    }
};
