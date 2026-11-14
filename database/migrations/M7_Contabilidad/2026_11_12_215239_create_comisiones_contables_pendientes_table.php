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
        Schema::create('comisiones_contables_pendientes', function (Blueprint $table): void {
    $table->id('id_comision_pendiente');

    $table->uuid('evento_id');
    $table->unsignedBigInteger('id_pedido');
    $table->unsignedBigInteger('id_subpedido');
    $table->unsignedBigInteger('id_comercio');

    $table->timestamp('fecha_evento');

    $table->string('estado', 20)->default('PENDIENTE');
    $table->unsignedInteger('intentos')->default(0);

    $table->decimal('monto_comision', 15, 2)->nullable();

    $table->text('ultimo_error')->nullable();
    $table->timestamp('fecha_procesamiento')->nullable();

    $table->timestamps();

    $table->unique('id_subpedido');
    $table->unique('evento_id');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comisiones_contables_pendientes');
    }
};
