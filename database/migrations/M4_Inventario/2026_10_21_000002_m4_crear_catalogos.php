<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M4 Inventario · PC3 sec. 6.4
 * Catálogos estados_reserva y motivos_ajuste con sus datos fijos.
 *
 * El SQL es el de la sección 6.5 (PostgreSQL). Los nombres de restricciones
 * siguen la convención de la sección 6.3. No usar migrate:fresh ni migrate:reset:
 * la base de datos de Supabase es compartida por todo el curso.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE estados_reserva (
    id_estado_reserva smallint     NOT NULL,
    codigo            varchar(30)  NOT NULL,
    nombre            varchar(60)  NOT NULL,
    descripcion       varchar(255),
    CONSTRAINT pk_estados_reserva PRIMARY KEY (id_estado_reserva),
    CONSTRAINT uq_estados_reserva_codigo UNIQUE (codigo)
);

CREATE TABLE motivos_ajuste (
    id_motivo_ajuste smallint     NOT NULL,
    codigo           varchar(30)  NOT NULL,
    nombre           varchar(60)  NOT NULL,
    descripcion      varchar(255),
    CONSTRAINT pk_motivos_ajuste PRIMARY KEY (id_motivo_ajuste),
    CONSTRAINT uq_motivos_ajuste_codigo UNIQUE (codigo)
);

INSERT INTO estados_reserva (id_estado_reserva, codigo, nombre) VALUES
    (1, 'activa',     'Activa'),
    (2, 'confirmada', 'Confirmada'),
    (3, 'consumida',  'Consumida'),
    (4, 'liberada',   'Liberada'),
    (5, 'vencida',    'Vencida');

INSERT INTO motivos_ajuste (id_motivo_ajuste, codigo, nombre) VALUES
    (1, 'merma',        'Merma'),
    (2, 'robo',         'Robo'),
    (3, 'danio',        'Daño'),
    (4, 'sobrante',     'Sobrante'),
    (5, 'error_conteo', 'Error de conteo'),
    (6, 'otro',         'Otro');
SQL
        );
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS motivos_ajuste');
        DB::unprepared('DROP TABLE IF EXISTS estados_reserva');
    }
};
