<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_cliente_pedido', function (Blueprint $table) {
            $table->id('id_nota');
            $table->foreignId('id_pedido')->references('id_pedido')->on('pedidos_maestros')->onDelete('cascade');
            $table->text('nota');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_cliente_pedido');
    }
};
