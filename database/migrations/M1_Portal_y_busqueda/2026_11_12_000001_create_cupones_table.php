<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Cupones de descuento (contrato 1.5.0, SC-11).
 * M1 es el dueño de los datos; M5 los valida y registra su uso por la
 * interfaz PHP de M1 al confirmar el pedido.
 *
 * - id_comercio: NULL es un cupón global de la plataforma (lo crea la
 *   administración y descuenta sobre todo el subtotal); con valor es un cupón
 *   de ese comercio y descuenta solo sobre el subtotal de sus productos.
 * - codigo: único en toda la plataforma, en mayúsculas.
 * - tipo_descuento: 'porcentaje' (valor mayor que 0 y menor que 100) o
 *   'monto_fijo' (valor en colones, mayor que 0).
 * - monto_minimo_compra: subtotal mínimo para que aplique; NULL = sin mínimo.
 * - limite_usos_total: NULL = sin límite. usos_actuales lo lleva M1 al
 *   registrar cada uso (tabla cupon_usos).
 * - activo: quien lo creó (o la administración) lo desactiva; no se reactiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupones', function (Blueprint $table) {
            $table->id('id_cupon');
            $table->unsignedBigInteger('id_comercio')->nullable();
            $table->string('codigo', 50);
            $table->string('descripcion', 255)->nullable();
            $table->string('tipo_descuento', 20);
            $table->decimal('valor', 10, 2);
            $table->decimal('monto_minimo_compra', 10, 2)->nullable();
            $table->timestamp('fecha_inicio');
            $table->timestamp('fecha_fin');
            $table->integer('limite_usos_total')->nullable();
            $table->integer('usos_actuales')->default(0);
            $table->integer('limite_usos_por_cliente')->default(1);
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('id_usuario_crea');
            $table->timestamps();

            $table->unique('codigo', 'uq_cupones_codigo');
            $table->index('id_comercio', 'ix_cupones_comercio');
            $table->index(['fecha_inicio', 'fecha_fin'], 'ix_cupones_vigencia');
            $table->foreign('id_comercio', 'fk_cupones_comercios')
                ->references('id_comercio')->on('comercios');
            $table->foreign('id_usuario_crea', 'fk_cupones_usuarios')
                ->references('id_usuario')->on('usuarios');
        });

        DB::statement("ALTER TABLE cupones ADD CONSTRAINT chk_cupones_tipo
                       CHECK (tipo_descuento IN ('porcentaje', 'monto_fijo'))");
        DB::statement("ALTER TABLE cupones ADD CONSTRAINT chk_cupones_valor
                       CHECK (valor > 0 AND (tipo_descuento <> 'porcentaje' OR valor < 100))");
        DB::statement('ALTER TABLE cupones ADD CONSTRAINT chk_cupones_codigo
                       CHECK (codigo = UPPER(codigo) AND char_length(codigo) >= 3)');
        DB::statement('ALTER TABLE cupones ADD CONSTRAINT chk_cupones_minimo
                       CHECK (monto_minimo_compra IS NULL OR monto_minimo_compra >= 0)');
        DB::statement('ALTER TABLE cupones ADD CONSTRAINT chk_cupones_vigencia
                       CHECK (fecha_fin > fecha_inicio)');
        DB::statement('ALTER TABLE cupones ADD CONSTRAINT chk_cupones_usos
                       CHECK (usos_actuales >= 0
                              AND limite_usos_por_cliente >= 1
                              AND (limite_usos_total IS NULL OR (limite_usos_total >= 1 AND usos_actuales <= limite_usos_total)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('cupones');
    }
};
