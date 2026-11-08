<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M4 · PC4 · Ajustes del modelo de datos para la construcción.
 *
 * 1. kardex_inventario.costo_promedio: costo promedio ponderado vigente después del
 *    movimiento. Con él se calcula saldo_valorizado = saldo_cantidad × costo_promedio
 *    (LineaKardex del contrato) sin reconstruir el kardex.
 * 2. uq_movimientos_referencia: un solo movimiento de cada tipo por referencia
 *    (id_reserva). Es la garantía en base de datos de que una salida, liberación o
 *    vencimiento no se registra dos veces (RNF-M4-010, sección 6.2 del PC3).
 * 3. eventos_procesados_inventario: id_evento de los eventos de otros módulos ya
 *    aplicados por M4, para que los listeners sean idempotentes (sección 4.1 del PC3).
 * 4. estados_comercio_inventario: último estado del comercio recibido de M2
 *    (ComercioSuspendido/ComercioReactivado) para bloquear movimientos manuales (RN-M4-009).
 * 5. Índices para el vencimiento de reservas cada 60 s (RNF-M4-007) y para buscar
 *    las reservas de un pedido o subpedido (listeners de M5 y M6).
 * 6. ajustes_inventario.cantidad_sistema y justificacion: el esquema Ajuste del
 *    contrato devuelve la cantidad que había, la diferencia y la justificación aunque
 *    el conteo no genere movimiento (pedido de Maikol).
 *
 * Solo PostgreSQL. No usar migrate:fresh ni migrate:reset: la base de Supabase es compartida.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE kardex_inventario
    ADD COLUMN costo_promedio numeric(14,4) NOT NULL DEFAULT 0;
ALTER TABLE kardex_inventario
    ADD CONSTRAINT ck_kardex_costo_promedio_no_negativo CHECK (costo_promedio >= 0);

CREATE UNIQUE INDEX uq_movimientos_referencia
    ON movimientos_inventario (id_comercio, tipo, referencia)
    WHERE referencia IS NOT NULL;

CREATE TABLE eventos_procesados_inventario (
    id_evento       varchar(64)  NOT NULL,
    nombre_evento   varchar(60)  NOT NULL,
    fecha_procesado timestamptz  NOT NULL DEFAULT now(),
    CONSTRAINT pk_eventos_procesados_inventario PRIMARY KEY (id_evento)
);

CREATE TABLE estados_comercio_inventario (
    id_comercio  bigint       NOT NULL,
    bloqueado    boolean      NOT NULL DEFAULT false,
    motivo       varchar(255),
    fecha_evento timestamptz  NOT NULL,
    id_evento    varchar(64)  NOT NULL,
    CONSTRAINT pk_estados_comercio_inventario PRIMARY KEY (id_comercio)
);

CREATE INDEX ix_reservas_activas_vencimiento
    ON reservas (fecha_vencimiento) WHERE id_estado_reserva = 1;
CREATE INDEX ix_reservas_pedido ON reservas (id_pedido);
CREATE INDEX ix_reservas_subpedido ON reservas (id_subpedido);

ALTER TABLE ajustes_inventario
    ADD COLUMN cantidad_sistema numeric(14,3) NOT NULL DEFAULT 0,
    ADD COLUMN justificacion varchar(255) NOT NULL DEFAULT '';
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE ajustes_inventario DROP COLUMN IF EXISTS justificacion;
ALTER TABLE ajustes_inventario DROP COLUMN IF EXISTS cantidad_sistema;
DROP INDEX IF EXISTS ix_reservas_subpedido;
DROP INDEX IF EXISTS ix_reservas_pedido;
DROP INDEX IF EXISTS ix_reservas_activas_vencimiento;
DROP TABLE IF EXISTS estados_comercio_inventario;
DROP TABLE IF EXISTS eventos_procesados_inventario;
DROP INDEX IF EXISTS uq_movimientos_referencia;
ALTER TABLE kardex_inventario DROP CONSTRAINT IF EXISTS ck_kardex_costo_promedio_no_negativo;
ALTER TABLE kardex_inventario DROP COLUMN IF EXISTS costo_promedio;
SQL
        );
    }
};
