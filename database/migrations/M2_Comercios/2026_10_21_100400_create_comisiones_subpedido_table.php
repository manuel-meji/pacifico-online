<?php

// M2 — Micrositios y administración de comercios (SG2)
// Comisión calculada por cada subpedido despachado (RN-010, paso 9 del recorrido).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comisiones_subpedido', function (Blueprint $table) {
            $table->id('id_comision_subpedido');
            $table->unsignedBigInteger('id_subpedido')->unique('uq_comision_subpedido')->comment('Referencia lógica a pedidos_subpedidos (M5)');
            $table->unsignedBigInteger('id_comercio');
            $table->decimal('subtotal_base', 12, 2);
            $table->decimal('monto', 12, 2);
            $table->char('moneda', 3)->default('CRC');
            $table->uuid('id_evento')->unique('uq_comision_subpedido_evento');
            $table->timestamps();
            $table->foreign('id_comercio', 'fk_comisiones_subpedido_id_comercio')->references('id_comercio')->on('comercios')->restrictOnDelete();
        });

        // Restricciones que implementan reglas de negocio (sección 6.3)
        DB::statement("ALTER TABLE comisiones_subpedido ADD CONSTRAINT chk_comision_subpedido_subtotal CHECK (subtotal_base >= 0)"); // RN-010
        DB::statement("ALTER TABLE comisiones_subpedido ADD CONSTRAINT chk_comision_subpedido_monto CHECK (monto >= 0)"); // RN-010
    }

    public function down(): void
    {
        Schema::dropIfExists('comisiones_subpedido');
    }
};
