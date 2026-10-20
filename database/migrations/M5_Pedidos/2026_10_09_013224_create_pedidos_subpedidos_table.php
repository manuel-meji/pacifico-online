<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            $table->string('guia_seguimiento', 255)->nullable();
            $table->timestamp('fecha_despacho')->nullable();
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE pedidos_subpedidos ADD CONSTRAINT subtotal_positivo CHECK (subtotal >= 0)');
            DB::statement('ALTER TABLE pedidos_subpedidos ADD CONSTRAINT costo_envio_positivo CHECK (costo_envio >= 0)');
            DB::statement('ALTER TABLE pedidos_subpedidos ADD CONSTRAINT impuesto_positivo CHECK (impuesto >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_subpedidos');
    }
};
