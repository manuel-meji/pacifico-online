<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancelaciones_pedido', function (Blueprint $table) {
            $table->id('id_cancelacion');
            $table->foreignId('id_pedido')->references('id_pedido')->on('pedidos_maestros')->onDelete('cascade');
            $table->text('motivo');
            $table->boolean('requiere_reembolso');
            $table->timestamp('fecha_cancelacion');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancelaciones_pedido');
    }
};
