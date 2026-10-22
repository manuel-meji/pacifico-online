<?php

// M2 — Micrositios y administración de comercios (SG2)
// Horario de atención por día (CU-0006).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios_comercio', function (Blueprint $table) {
            $table->id('id_horario');
            $table->unsignedBigInteger('id_comercio');
            $table->smallInteger('dia_semana');
            $table->time('hora_apertura')->nullable();
            $table->time('hora_cierre')->nullable();
            $table->boolean('cerrado')->default(false);
            $table->timestamps();
            $table->unique(['id_comercio', 'dia_semana'], 'uq_horarios_comercio_dia');
            $table->foreign('id_comercio', 'fk_horarios_comercio_id_comercio')->references('id_comercio')->on('comercios')->restrictOnDelete();
        });

        // Restricciones que implementan reglas de negocio (sección 6.3)
        DB::statement("ALTER TABLE horarios_comercio ADD CONSTRAINT chk_horarios_dia CHECK (dia_semana BETWEEN 1 AND 7)"); // 1 = lunes, 7 = domingo
        DB::statement("ALTER TABLE horarios_comercio ADD CONSTRAINT chk_horarios_horas CHECK (cerrado OR (hora_apertura IS NOT NULL AND hora_cierre IS NOT NULL AND hora_apertura < hora_cierre))"); // Horas coherentes si abre
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios_comercio');
    }
};
