<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Promociones de los comercios (contrato 1.4.0, SC-08).
 * M1 es el dueño de los datos; cada comercio crea y gestiona las suyas.
 *
 * - tipo: 'porcentaje' (valor entre 0 y 100, sin incluirlos) o 'precio_fijo'
 *   (valor mayor que 0 y menor que el precio de cada variación afectada).
 * - alcance: 'producto' (todas sus variaciones) o 'variaciones' (las de
 *   promocion_variaciones).
 * - fecha_inicio y fecha_fin obligatorias: se activa y desactiva sola.
 * - activa: el comercio la desactiva. oculta: la plataforma la oculta por
 *   incumplir las políticas; mientras está oculta no rebaja el precio.
 * - orden: posición que decide la plataforma en la sección del portal.
 *
 * Reemplaza a promociones_portal (pieza de carrusel sin descuento), que queda
 * en desuso y no se borra porque la base es compartida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promociones', function (Blueprint $table) {
            $table->id('id_promocion');
            $table->unsignedBigInteger('id_comercio');
            $table->unsignedBigInteger('id_producto');
            $table->string('titulo', 120);
            $table->string('descripcion', 500)->nullable();
            $table->string('tipo', 20);
            $table->decimal('valor', 12, 2);
            $table->string('alcance', 20);
            $table->timestamp('fecha_inicio');
            $table->timestamp('fecha_fin');
            $table->boolean('activa')->default(true);
            $table->boolean('oculta')->default(false);
            $table->string('motivo_ocultamiento', 500)->nullable();
            $table->smallInteger('orden')->nullable();
            $table->unsignedBigInteger('id_usuario_crea');
            $table->timestamps();

            $table->index(['id_producto', 'activa'], 'ix_promociones_producto');
            $table->index(['id_comercio'], 'ix_promociones_comercio');
            $table->index(['fecha_inicio', 'fecha_fin'], 'ix_promociones_vigencia');
            $table->foreign('id_comercio', 'fk_promociones_comercios')
                ->references('id_comercio')->on('comercios');
            $table->foreign('id_producto', 'fk_promociones_productos')
                ->references('id_producto')->on('productos');
            $table->foreign('id_usuario_crea', 'fk_promociones_usuarios')
                ->references('id_usuario')->on('usuarios');
        });

        DB::statement("ALTER TABLE promociones ADD CONSTRAINT chk_promociones_tipo
                       CHECK (tipo IN ('porcentaje', 'precio_fijo'))");
        DB::statement("ALTER TABLE promociones ADD CONSTRAINT chk_promociones_alcance
                       CHECK (alcance IN ('producto', 'variaciones'))");
        DB::statement("ALTER TABLE promociones ADD CONSTRAINT chk_promociones_valor
                       CHECK (valor > 0 AND (tipo <> 'porcentaje' OR valor < 100))");
        DB::statement('ALTER TABLE promociones ADD CONSTRAINT chk_promociones_vigencia
                       CHECK (fecha_fin > fecha_inicio)');
        DB::statement('ALTER TABLE promociones ADD CONSTRAINT chk_promociones_orden
                       CHECK (orden IS NULL OR orden > 0)');
        DB::statement('ALTER TABLE promociones ADD CONSTRAINT chk_promociones_ocultamiento
                       CHECK (oculta = false OR motivo_ocultamiento IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('promociones');
    }
};
