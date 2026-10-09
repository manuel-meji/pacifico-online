<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_maestros', function (Blueprint $table) {
            $table->id('id_pedido');
            $table->unsignedBigInteger('id_cliente');
            $table->timestamp('fecha_creacion');
            $table->decimal('monto_total', 10, 2);
            $table->string('estado_pedido', 50);
            $table->string('cupon_descuento', 50)->nullable();
            $table->string('tarjeta_regalo', 50)->nullable();
            $table->timestamps();
        });
        
        DB::statement('ALTER TABLE pedidos_maestros ADD CONSTRAINT monto_total_positivo CHECK (monto_total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_maestros');
    }
};
