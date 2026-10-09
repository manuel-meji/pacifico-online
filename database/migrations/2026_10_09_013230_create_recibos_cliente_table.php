<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos_cliente', function (Blueprint $table) {
            $table->id('id_recibo');
            $table->foreignId('id_pedido')->references('id_pedido')->on('pedidos_maestros')->onDelete('cascade');
            $table->string('url_recibo', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos_cliente');
    }
};
