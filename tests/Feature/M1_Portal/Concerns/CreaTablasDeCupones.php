<?php

namespace Tests\Feature\M1_Portal\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara las pruebas de cupones de M1 (contrato 1.5.0, SC-11) en el SQLite
 * en memoria del proyecto.
 *
 * Las migraciones de M1 usan SQL de PostgreSQL (CHECK y llaves foráneas hacia
 * M2 y el núcleo) que no corre en SQLite, así que se crea una versión simple
 * de las tablas con las mismas columnas. Se prueba la lógica de M1; las
 * restricciones de la base se validan contra el ambiente.
 */
trait CreaTablasDeCupones
{
    protected function prepararCupones(): void
    {
        Schema::create('cupones', function (Blueprint $tabla): void {
            $tabla->id('id_cupon');
            $tabla->unsignedBigInteger('id_comercio')->nullable();
            $tabla->string('codigo', 50)->unique();
            $tabla->string('descripcion', 255)->nullable();
            $tabla->string('tipo_descuento', 20);
            $tabla->decimal('valor', 10, 2);
            $tabla->decimal('monto_minimo_compra', 10, 2)->nullable();
            $tabla->timestamp('fecha_inicio');
            $tabla->timestamp('fecha_fin');
            $tabla->integer('limite_usos_total')->nullable();
            $tabla->integer('usos_actuales')->default(0);
            $tabla->integer('limite_usos_por_cliente')->default(1);
            $tabla->boolean('activo')->default(true);
            $tabla->unsignedBigInteger('id_usuario_crea');
            $tabla->timestamps();
        });

        Schema::create('cupon_usos', function (Blueprint $tabla): void {
            $tabla->id('id_cupon_uso');
            $tabla->unsignedBigInteger('id_cupon');
            $tabla->unsignedBigInteger('id_cliente');
            $tabla->unsignedBigInteger('id_pedido');
            $tabla->timestamp('created_at')->useCurrent();
            $tabla->unique(['id_cupon', 'id_pedido']);
        });
    }
}
