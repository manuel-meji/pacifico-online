<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_pedido', function (Blueprint $table) {
            $table->id('id_estado_pedido');
            $table->foreignId('id_pedido')->references('id_pedido')->on('pedidos_maestros')->onDelete('cascade');
            $table->string('estado', 50);
            $table->timestamp('fecha_estado');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_pedido');
    }
};
