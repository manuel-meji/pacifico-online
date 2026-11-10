<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_deseos', function (Blueprint $table) {
            $table->id('id_deseo');
            $table->unsignedBigInteger('id_cliente');
            $table->unsignedBigInteger('id_variacion');
            $table->timestamps();

            $table->unique(['id_cliente', 'id_variacion'], 'unique_cliente_variacion_deseo');
            $table->index('id_cliente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_deseos');
    }
};
