<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Cargo por cada destacado pagado que se aprueba
 * (contrato 1.4.0, SC-08). No se paga aparte: quien liquida al comercio lo
 * descuenta, consultándolo con ComercialPortalContract::listarCargosDestacado.
 * El periodo corre aunque el producto se oculte y no se reembolsa, así que el
 * cargo no se modifica después de creado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos_destacado', function (Blueprint $table) {
            $table->id('id_cargo_destacado');
            $table->unsignedBigInteger('id_comercio');
            $table->unsignedBigInteger('id_solicitud_destacado');
            $table->unsignedBigInteger('id_producto_destacado');
            $table->unsignedBigInteger('id_producto');
            $table->smallInteger('periodo_dias');
            $table->decimal('monto', 12, 2);
            $table->char('moneda', 3)->default('CRC');
            $table->timestamp('fecha_cargo');
            $table->timestamp('fecha_inicio');
            $table->timestamp('fecha_fin');
            $table->timestamps();

            $table->unique('id_solicitud_destacado', 'uq_cargos_destacado_solicitud');
            $table->index(['id_comercio', 'fecha_cargo'], 'ix_cargos_destacado_comercio_fecha');
            $table->foreign('id_comercio', 'fk_cargos_destacado_comercios')
                ->references('id_comercio')->on('comercios');
            $table->foreign('id_solicitud_destacado', 'fk_cargos_destacado_solicitudes')
                ->references('id_solicitud_destacado')->on('solicitudes_destacado');
            $table->foreign('id_producto_destacado', 'fk_cargos_destacado_productos_destacados')
                ->references('id_producto_destacado')->on('productos_destacados');
            $table->foreign('id_producto', 'fk_cargos_destacado_productos')
                ->references('id_producto')->on('productos');
        });

        DB::statement('ALTER TABLE cargos_destacado ADD CONSTRAINT chk_cargos_destacado_monto
                       CHECK (monto > 0)');
        DB::statement('ALTER TABLE cargos_destacado ADD CONSTRAINT chk_cargos_destacado_periodo
                       CHECK (periodo_dias IN (7, 15, 30))');
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos_destacado');
    }
};
