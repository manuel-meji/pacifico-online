<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_subpedidos', function (Blueprint $table) {
            $table->id('id_subpedido');
            $table->foreignId('id_pedido')->references('id_pedido')->on('pedidos_maestros')->onDelete('cascade');
            $table->unsignedBigInteger('id_comercio');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('costo_envio', 10, 2);
            $table->decimal('impuesto', 10, 2);
            $table->string('estado_subpedido', 50);
            $table->timestamps();
        });
        
        DB::statement('ALTER TABLE pedidos_subpedidos ADD CONSTRAINT subtotal_positivo CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE pedidos_subpedidos ADD CONSTRAINT costo_envio_positivo CHECK (costo_envio >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_subpedidos');
    }
};
