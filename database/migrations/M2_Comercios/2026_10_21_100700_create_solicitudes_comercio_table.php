<?php

// M2 — Micrositios y administración de comercios (SG2)
// Trámite de incorporación del comercio (CU-0012 a CU-0016).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_comercio', function (Blueprint $table) {
            $table->id('id_solicitud');
            $table->unsignedBigInteger('id_comercio');
            $table->string('nombre_encargado', 150);
            $table->string('correo_encargado', 150);
            $table->string('telefono_encargado', 20)->nullable();
            $table->string('estado', 15)->default('Pendiente');
            $table->boolean('requiere_documentos')->default(false);
            $table->text('motivo_rechazo')->nullable();
            $table->timestamp('fecha_reenvio')->nullable();
            $table->timestamps();
            $table->foreign('id_comercio', 'fk_solicitudes_comercio_id_comercio')->references('id_comercio')->on('comercios')->restrictOnDelete();
        });

        // Restricciones que implementan reglas de negocio (sección 6.3)
        DB::statement("ALTER TABLE solicitudes_comercio ADD CONSTRAINT chk_solicitudes_estado CHECK (estado IN ('Pendiente', 'Aprobada', 'Rechazada'))"); // Estados de la solicitud
        DB::statement("ALTER TABLE solicitudes_comercio ADD CONSTRAINT chk_solicitud_motivo_rechazo CHECK (estado <> 'Rechazada' OR (motivo_rechazo IS NOT NULL AND length(trim(motivo_rechazo)) > 0))"); // RN-016
        DB::statement("CREATE UNIQUE INDEX uq_solicitud_pendiente_comercio ON solicitudes_comercio (id_comercio) WHERE estado = 'Pendiente'");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_comercio');
    }
};
