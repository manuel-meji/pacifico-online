<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_maestros', function (Blueprint $table) {
            $table->id('id_pedido');
            $table->unsignedBigInteger('id_cliente');
            $table->unsignedBigInteger('id_direccion_cliente')->nullable();
            $table->timestamp('fecha_creacion');
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('costo_envio', 10, 2)->default(0.00);
            $table->decimal('impuesto', 10, 2)->default(0.00);
            $table->decimal('descuento', 10, 2)->default(0.00);
            $table->decimal('monto_total', 10, 2)->default(0.00);
            $table->string('estado_pedido', 50)->default('pendiente');
            $table->string('cupon_descuento', 50)->nullable();
            $table->string('tarjeta_regalo', 50)->nullable();
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE pedidos_maestros ADD CONSTRAINT chk_pedidos_maestros_monto_total_positivo CHECK (monto_total >= 0)');
            DB::statement('ALTER TABLE pedidos_maestros ADD CONSTRAINT chk_pedidos_maestros_subtotal_positivo CHECK (subtotal >= 0)');
            DB::statement('ALTER TABLE pedidos_maestros ADD CONSTRAINT chk_pedidos_maestros_costo_envio_positivo CHECK (costo_envio >= 0)');
            DB::statement('ALTER TABLE pedidos_maestros ADD CONSTRAINT chk_pedidos_maestros_impuesto_positivo CHECK (impuesto >= 0)');
            DB::statement('ALTER TABLE pedidos_maestros ADD CONSTRAINT chk_pedidos_maestros_descuento_positivo CHECK (descuento >= 0)');
            DB::statement('ALTER TABLE pedidos_maestros ADD CONSTRAINT chk_pedidos_maestros_cuadratura CHECK (monto_total = subtotal + costo_envio + impuesto - descuento)');
            DB::statement("ALTER TABLE pedidos_maestros ADD CONSTRAINT chk_pedidos_maestros_estados CHECK (estado_pedido IN ('pendiente', 'confirmado', 'pagado', 'en_preparacion', 'despachado', 'entregado', 'cancelado'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_maestros');
    }
};
