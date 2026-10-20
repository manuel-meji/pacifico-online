<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_lineas', function (Blueprint $table) {
            $table->id('id_linea');
            $table->foreignId('id_subpedido')->references('id_subpedido')->on('pedidos_subpedidos')->onDelete('cascade');
            $table->unsignedBigInteger('id_variacion');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('tarifa_impuesto', 5, 2);
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE pedidos_lineas ADD CONSTRAINT cantidad_positiva CHECK (cantidad > 0)');
            DB::statement('ALTER TABLE pedidos_lineas ADD CONSTRAINT precio_unitario_positivo CHECK (precio_unitario >= 0)');
            DB::statement('ALTER TABLE pedidos_lineas ADD CONSTRAINT tarifa_impuesto_positiva CHECK (tarifa_impuesto >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_lineas');
    }
};
