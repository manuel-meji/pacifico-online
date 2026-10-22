<?php

namespace Tests\Feature\M4_Inventario;

use Illuminate\Support\Facades\DB;

/**
 * Crea en SQLite las tablas de M4 con las mismas columnas y restricciones de
 * las migraciones 2026_10_21_00000x_m4_* (que son SQL de PostgreSQL y no corren
 * en la base de pruebas en memoria). Si cambia una migración, cambiar aquí.
 */
trait EsquemaInventarioPrueba
{
    protected function crearEsquemaInventario(): void
    {
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
    cantidad_defectuosa numeric(14,3) NOT NULL DEFAULT 0,
    stock_minimo numeric(14,3) NOT NULL DEFAULT 0,
    costo_promedio numeric(14,4) NOT NULL DEFAULT 0,
    created_at timestamp,
    updated_at timestamp,
    UNIQUE (id_comercio, id_variacion, id_ubicacion),
    UNIQUE (id_comercio, id_existencia),
    CHECK (cantidad_fisica >= 0),
    CHECK (cantidad_reservada >= 0),
    CHECK (cantidad_fisica - cantidad_reservada - cantidad_defectuosa >= 0)
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
SQL
        );
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
            'cantidad_defectuosa' => 0,
            'stock_minimo' => 0,
        ], 'id_existencia');
    }
}
