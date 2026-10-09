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
        Schema::create('pedidos_lineas', function (Blueprint $table) {$table->id('id_linea');
            $table->foreignId('id_subpedido')->references('id_subpedido')->on('pedidos_subpedidos')->onDelete('cascade');$table->foreignId('id_variacion')->constrained('variaciones');
            $table->integer('cantidad');$table->decimal('precio_unitario', 10, 2);
            $table->decimal('tarifa_impuesto', 5, 2);$table->timestamps();

            // Reglas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos_lineas');
    }
};
