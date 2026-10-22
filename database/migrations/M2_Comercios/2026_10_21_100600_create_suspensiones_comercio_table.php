<?php

// M2 — Micrositios y administración de comercios (SG2)
// Historial de suspensiones con motivo obligatorio (RN-004, RN-005, CU-0017, CU-0018).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suspensiones_comercio', function (Blueprint $table) {
            $table->id('id_suspension');
            $table->unsignedBigInteger('id_comercio');
            $table->unsignedBigInteger('id_usuario')->comment('Referencia lógica a usuarios (Núcleo)');
            $table->text('motivo');
            $table->timestamp('fecha_suspension')->useCurrent();
            $table->timestamp('fecha_reactivacion')->nullable();
            $table->timestamps();
            $table->foreign('id_comercio', 'fk_suspensiones_comercio_id_comercio')->references('id_comercio')->on('comercios')->restrictOnDelete();
        });

        // Restricciones que implementan reglas de negocio (sección 6.3)
        DB::statement("ALTER TABLE suspensiones_comercio ADD CONSTRAINT chk_suspensiones_motivo CHECK (length(trim(motivo)) > 0)"); // RN-005
        DB::statement("ALTER TABLE suspensiones_comercio ADD CONSTRAINT chk_suspensiones_fechas CHECK (fecha_reactivacion IS NULL OR fecha_reactivacion >= fecha_suspension)"); // Reactivación posterior a la suspensión
        DB::statement("CREATE UNIQUE INDEX uq_suspension_abierta_comercio ON suspensiones_comercio (id_comercio) WHERE fecha_reactivacion IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('suspensiones_comercio');
    }
};
