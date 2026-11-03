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
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('costo_envio', 10, 2)->default(0.00);
            $table->decimal('impuesto', 10, 2)->default(0.00);
            $table->decimal('descuento', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2)->default(0.00);
            $table->string('estado_subpedido', 50)->default('pendiente');
            $table->string('guia_seguimiento', 255)->nullable();
            $table->timestamp('fecha_despacho')->nullable();
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE pedidos_subpedidos ADD CONSTRAINT chk_pedidos_subpedidos_subtotal_positivo CHECK (subtotal >= 0)');
            DB::statement('ALTER TABLE pedidos_subpedidos ADD CONSTRAINT chk_pedidos_subpedidos_costo_envio_positivo CHECK (costo_envio >= 0)');
            DB::statement('ALTER TABLE pedidos_subpedidos ADD CONSTRAINT chk_pedidos_subpedidos_impuesto_positivo CHECK (impuesto >= 0)');
            DB::statement('ALTER TABLE pedidos_subpedidos ADD CONSTRAINT chk_pedidos_subpedidos_descuento_positivo CHECK (descuento >= 0)');
            DB::statement('ALTER TABLE pedidos_subpedidos ADD CONSTRAINT chk_pedidos_subpedidos_total_positivo CHECK (total >= 0)');
            DB::statement('ALTER TABLE pedidos_subpedidos ADD CONSTRAINT chk_pedidos_subpedidos_cuadratura CHECK (total = subtotal + costo_envio + impuesto - descuento)');
            DB::statement("ALTER TABLE pedidos_subpedidos ADD CONSTRAINT chk_pedidos_subpedidos_estados CHECK (estado_subpedido IN ('pendiente', 'confirmado', 'pagado', 'en_preparacion', 'despachado', 'entregado', 'cancelado'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_subpedidos');
    }
};
