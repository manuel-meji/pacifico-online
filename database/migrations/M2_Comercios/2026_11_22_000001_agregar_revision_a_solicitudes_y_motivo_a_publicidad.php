<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisión exhaustiva de M2 (22/11):
 *  - solicitudes_comercio guarda quién y cuándo aprobó o rechazó la solicitud, y el último motivo de
 *    rechazo, para mostrar el historial (rechazada, corregida y aprobada) en el detalle. revisado_por
 *    y registrada_por son referencias lógicas a usuarios del núcleo, sin llave foránea (decisión 1.9 del Comité).
 *  - solicitudes_comercio también guarda quién la registró cuando la registra la plataforma en nombre
 *    del comercio (pestaña «Registrar comercio»), para que el detalle lo diga.
 *  - publicidad_comercio guarda el motivo con que la plataforma rechaza o retira un destacado.
 *
 * Solo agrega columnas que aceptan null: las filas existentes no cambian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_comercio', function (Blueprint $table) {
            $table->timestamp('fecha_revision')->nullable();
            $table->unsignedBigInteger('revisado_por')->nullable();
            $table->string('nombre_revisor', 150)->nullable();
            $table->text('ultimo_motivo_rechazo')->nullable();
            $table->unsignedBigInteger('registrada_por')->nullable();
            $table->string('nombre_registrador', 150)->nullable();
        });

        Schema::table('publicidad_comercio', function (Blueprint $table) {
            $table->text('motivo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_comercio', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_revision', 'revisado_por', 'nombre_revisor', 'ultimo_motivo_rechazo', 'registrada_por', 'nombre_registrador',
            ]);
        });

        Schema::table('publicidad_comercio', function (Blueprint $table) {
            $table->dropColumn('motivo');
        });
    }
};
