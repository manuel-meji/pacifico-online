<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_pagar_comercio_pendientes', function (Blueprint $table): void {
            $table->id('id_cuenta_pagar_pendiente');

            $table->uuid('evento_id')->unique();
            $table->unsignedBigInteger('id_pedido');
            $table->unsignedBigInteger('id_subpedido')->unique();
            $table->unsignedBigInteger('id_comercio');

            $table->timestamp('fecha_evento');

            $table->string('estado', 20)->default('PENDIENTE');
            $table->unsignedInteger('intentos')->default(0);

            $table->text('ultimo_error')->nullable();
            $table->timestamp('fecha_procesamiento')->nullable();

            $table->timestamps();

            $table->index(['estado', 'id_subpedido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_pagar_comercio_pendientes');
    }
};