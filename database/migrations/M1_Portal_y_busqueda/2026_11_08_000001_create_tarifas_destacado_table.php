<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Tarifa fija de cada periodo de producto destacado (7, 15 o
 * 30 días), que define la administración de la plataforma (contrato 1.4.0, SC-08).
 * Una fila por periodo. id_usuario_actualiza es la cuenta del núcleo que la
 * cambió por última vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas_destacado', function (Blueprint $table) {
            $table->id('id_tarifa_destacado');
            $table->smallInteger('periodo_dias');
            $table->decimal('monto', 12, 2);
            $table->char('moneda', 3)->default('CRC');
            $table->unsignedBigInteger('id_usuario_actualiza')->nullable();
            $table->timestamps();

            $table->unique('periodo_dias', 'uq_tarifas_destacado_periodo');
            $table->foreign('id_usuario_actualiza', 'fk_tarifas_destacado_usuarios')
                ->references('id_usuario')->on('usuarios');
        });

        DB::statement('ALTER TABLE tarifas_destacado ADD CONSTRAINT chk_tarifas_destacado_periodo
                       CHECK (periodo_dias IN (7, 15, 30))');
        DB::statement('ALTER TABLE tarifas_destacado ADD CONSTRAINT chk_tarifas_destacado_monto
                       CHECK (monto >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas_destacado');
    }
};
