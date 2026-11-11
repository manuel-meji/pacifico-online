<?php

namespace Tests\Feature\M4_Inventario;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea en SQLite las tablas de M4 con las mismas columnas y restricciones de
 * las migraciones 2026_10_21_00000x_m4_*, 000008 y 000009 (que son SQL de PostgreSQL
 * y no corren en la base de pruebas en memoria). Si cambia una migración, cambiar aquí.
 * Los triggers de inmutabilidad y de transición de estados solo existen en
 * PostgreSQL; en SQLite los cubren los modelos y el patrón State.
 */
trait EsquemaInventarioPrueba
{
    protected function crearEsquemaInventario(): void
    {
        if (Schema::hasTable('existencias')) {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE TABLE estados_reserva (
    id_estado_reserva integer PRIMARY KEY,
    codigo varchar(30) NOT NULL UNIQUE,
    nombre varchar(60) NOT NULL,
    descripcion varchar(255)
);
INSERT INTO estados_reserva (id_estado_reserva, codigo, nombre) VALUES
    (1, 'activa', 'Activa'), (2, 'confirmada', 'Confirmada'), (3, 'consumida', 'Consumida'),
    (4, 'liberada', 'Liberada'), (5, 'vencida', 'Vencida');

CREATE TABLE existencias (
    id_existencia integer PRIMARY KEY AUTOINCREMENT,
    id_comercio bigint NOT NULL,
    id_producto bigint NOT NULL,
    id_variacion bigint NOT NULL,
    id_ubicacion bigint NOT NULL,
    cantidad_fisica numeric(14,3) NOT NULL DEFAULT 0,
    cantidad_reservada numeric(14,3) NOT NULL DEFAULT 0,
    stock_minimo numeric(14,3) NOT NULL DEFAULT 0,
    costo_promedio numeric(14,4) NOT NULL DEFAULT 0,
    created_at timestamp,
    updated_at timestamp,
    UNIQUE (id_comercio, id_variacion, id_ubicacion),
    UNIQUE (id_comercio, id_producto, id_variacion, id_ubicacion),
    UNIQUE (id_comercio, id_existencia),
    CHECK (cantidad_fisica >= 0),
    CHECK (cantidad_reservada >= 0),
    CHECK (cantidad_fisica - cantidad_reservada >= 0)
);

CREATE TABLE reservas (
    id_reserva varchar(36) PRIMARY KEY,
    id_comercio bigint NOT NULL,
    id_existencia bigint NOT NULL REFERENCES existencias (id_existencia),
    id_estado_reserva smallint NOT NULL DEFAULT 1 REFERENCES estados_reserva (id_estado_reserva),
    id_pedido bigint,
    id_subpedido bigint,
    cantidad numeric(14,3) NOT NULL,
    fecha_creacion timestamp NOT NULL,
    fecha_vencimiento timestamp NOT NULL,
    clave_idempotencia varchar(100) NOT NULL,
    historial text NOT NULL DEFAULT '[]',
    UNIQUE (id_comercio, clave_idempotencia),
    CHECK (cantidad > 0),
    CHECK (fecha_vencimiento > fecha_creacion),
    CHECK (id_estado_reserva NOT IN (2, 3) OR id_pedido IS NOT NULL)
);

CREATE TABLE motivos_ajuste (
    id_motivo_ajuste integer PRIMARY KEY,
    codigo varchar(30) NOT NULL UNIQUE,
    nombre varchar(60) NOT NULL,
    descripcion varchar(255)
);
INSERT INTO motivos_ajuste (id_motivo_ajuste, codigo, nombre) VALUES
    (1, 'merma', 'Merma'), (2, 'robo', 'Robo'), (3, 'danio', 'Daño'),
    (4, 'sobrante', 'Sobrante'), (5, 'error_conteo', 'Error de conteo'), (6, 'otro', 'Otro');

CREATE TABLE movimientos_inventario (
    id_movimiento integer PRIMARY KEY AUTOINCREMENT,
    id_comercio bigint NOT NULL,
    id_producto bigint NOT NULL,
    id_variacion bigint NOT NULL,
    id_ubicacion bigint NOT NULL,
    id_usuario bigint NOT NULL,
    id_autorizador bigint,
    tipo varchar(40) NOT NULL,
    efecto varchar(10) NOT NULL,
    cantidad numeric(14,3) NOT NULL,
    costo_unitario numeric(14,4) NOT NULL DEFAULT 0,
    referencia varchar(100),
    motivo varchar(255) NOT NULL,
    fecha_hora timestamp NOT NULL,
    fecha_autorizacion timestamp,
    UNIQUE (id_comercio, id_movimiento),
    FOREIGN KEY (id_comercio, id_producto, id_variacion, id_ubicacion)
        REFERENCES existencias (id_comercio, id_producto, id_variacion, id_ubicacion),
    CHECK (costo_unitario >= 0),
    CHECK (tipo <> 'ajuste_compensatorio' OR referencia IS NOT NULL),
    CHECK (tipo NOT LIKE 'ajuste%' OR (id_autorizador IS NOT NULL AND fecha_autorizacion IS NOT NULL)),
    CHECK (tipo NOT LIKE 'transferencia%' OR id_autorizador IS NOT NULL)
);
CREATE UNIQUE INDEX uq_movimientos_referencia
    ON movimientos_inventario (id_comercio, tipo, referencia) WHERE referencia IS NOT NULL;

CREATE TABLE kardex_inventario (
    id_kardex integer PRIMARY KEY AUTOINCREMENT,
    id_comercio bigint NOT NULL,
    id_movimiento bigint NOT NULL UNIQUE,
    saldo_cantidad numeric(14,3) NOT NULL,
    saldo_reservado numeric(14,3) NOT NULL DEFAULT 0,
    monto_total numeric(16,2) NOT NULL,
    costo_promedio numeric(14,4) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_comercio, id_movimiento) REFERENCES movimientos_inventario (id_comercio, id_movimiento),
    CHECK (monto_total >= 0),
    CHECK (costo_promedio >= 0)
);

CREATE TABLE ajustes_inventario (
    id_ajuste integer PRIMARY KEY AUTOINCREMENT,
    id_comercio bigint NOT NULL,
    id_existencia bigint NOT NULL,
    id_motivo_ajuste smallint NOT NULL REFERENCES motivos_ajuste (id_motivo_ajuste),
    id_movimiento bigint UNIQUE,
    id_usuario bigint NOT NULL,
    cantidad_contada numeric(14,3) NOT NULL,
    cantidad_sistema numeric(14,3) NOT NULL DEFAULT 0,
    justificacion varchar(255) NOT NULL DEFAULT '',
    created_at timestamp NOT NULL,
    FOREIGN KEY (id_comercio, id_existencia) REFERENCES existencias (id_comercio, id_existencia),
    FOREIGN KEY (id_comercio, id_movimiento) REFERENCES movimientos_inventario (id_comercio, id_movimiento)
);

CREATE TABLE eventos_procesados_inventario (
    id_evento varchar(64) PRIMARY KEY,
    nombre_evento varchar(60) NOT NULL,
    fecha_procesado timestamp NOT NULL
);

CREATE TABLE estados_comercio_inventario (
    id_comercio bigint PRIMARY KEY,
    bloqueado boolean NOT NULL DEFAULT 0,
    motivo varchar(255),
    fecha_evento timestamp NOT NULL,
    id_evento varchar(64) NOT NULL
);
SQL
        );
    }

    /**
     * Tablas del núcleo (usuarios y bitácora de auditoría) para las pruebas que usan
     * el núcleo real: M4 registra en su bitácora las operaciones sensibles. La
     * bitácora tiene llave foránea a comercios (M2); en la base de prueba de M4 no
     * se crean las tablas de M2, así que se desactivan las llaves foráneas.
     */
    protected function crearEsquemaNucleo(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/Nucleo', '--force' => true]);
        Schema::disableForeignKeyConstraints();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function existencia(array $datos): int
    {
        return (int) DB::table('existencias')->insertGetId($datos + [
            'id_comercio' => 1,
            'id_producto' => 1,
            'cantidad_reservada' => 0,
            'stock_minimo' => 0,
        ], 'id_existencia');
    }
}
