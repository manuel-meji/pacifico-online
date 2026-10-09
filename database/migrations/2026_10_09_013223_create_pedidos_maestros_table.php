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
        Schema::create('pedidos_maestros', function (Blueprint $table) {
            $table->id('id_pedido');$table->foreignId('id_cliente')->constrained('clientes');
            $table->timestamp('fecha_creacion');$table->decimal('monto_total', 10, 2);
            $table->string('estado_pedido', 50);$table->string('cupon_descuento', 50)->nullable();
            $table->string('tarjeta_regalo', 50)->nullable();$table->timestamps();

            // Reglas
            $table->check('monto_total >= 0', 'monto_total_positivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos_maestros');
    }
};
