<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_pedido', function (Blueprint $table) {
            $table->id('id_estado_pedido');
            $table->foreignId('id_pedido')->references('id_pedido')->on('pedidos_maestros')->onDelete('cascade');
            $table->foreignId('id_subpedido')->nullable()->references('id_subpedido')->on('pedidos_subpedidos')->onDelete('cascade');
            $table->uuid('id_evento')->nullable()->unique();
            $table->string('estado_anterior', 50)->nullable();
            $table->string('estado', 50);
            if (Schema::hasTable('usuarios')) {
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios', 'id_usuario');
            } else {
                $table->unsignedBigInteger('usuario_id')->nullable();
            }
            $table->text('motivo')->nullable();
            $table->timestamp('fecha_estado');
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE estados_pedido ADD CONSTRAINT chk_estados_pedido_validos CHECK (estado IN ('pendiente', 'confirmado', 'pagado', 'en_preparacion', 'despachado', 'entregado', 'cancelado'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_pedido');
    }
};
