<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asientos_contables', function (Blueprint $table) {
            $table->id('id_asiento');

            // Claves compartidas externas.
            $table->unsignedBigInteger('id_comercio');
            $table->unsignedBigInteger('id_pedido')->nullable();
            $table->unsignedBigInteger('id_subpedido')->nullable();
            $table->unsignedBigInteger('id_pago')->nullable();

            // Evento que originó el asiento.
            $table->uuid('evento_id');
            $table->string('tipo_evento', 80);

            // Tipo contable del asiento.
            $table->string('tipo', 40);

            $table->timestamp('fecha')->useCurrent();

            $table->string('descripcion', 255)->nullable();

            $table->string('estado', 20)->default('REGISTRADO');

            // Para relacionar una reversión con el asiento original.
            $table->unsignedBigInteger('id_asiento_original')->nullable();

            $table->timestamps();

            $table->foreign('id_asiento_original')
                ->references('id_asiento')
                ->on('asientos_contables')
                ->nullOnDelete();

            $table->index('id_comercio');
            $table->index('id_pedido');
            $table->index('id_subpedido');
            $table->index('id_pago');
            $table->index('evento_id');
        });

        DB::statement("
            ALTER TABLE asientos_contables
            ADD CONSTRAINT chk_asientos_contables_estado
            CHECK (estado IN ('REGISTRADO', 'PENDIENTE', 'ANULADO'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('asientos_contables');
    }
};