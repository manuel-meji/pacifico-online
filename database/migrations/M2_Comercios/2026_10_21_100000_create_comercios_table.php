<?php

// M2 — Micrositios y administración de comercios (SG2)
// Tabla principal de M2: datos base y estado operativo del comercio.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comercios', function (Blueprint $table) {
            $table->id('id_comercio');
            $table->string('cedula_juridica', 12)->unique('uq_comercios_cedula_juridica');
            $table->string('nombre_comercial', 150);
            $table->string('razon_social', 150);
            $table->string('direccion_fiscal', 255);
            $table->boolean('inscrito_como_contribuyente')->default(false);
            $table->string('estado', 15)->default('Pendiente');
            $table->timestamps();
        });

        // Restricciones que implementan reglas de negocio (sección 6.3)
        DB::statement("ALTER TABLE comercios ADD CONSTRAINT chk_comercios_estado CHECK (estado IN ('Pendiente', 'Activo', 'Rechazado', 'Suspendido'))"); // Estados del comercio (glosario)
        DB::statement("ALTER TABLE comercios ADD CONSTRAINT chk_comercios_cedula_formato CHECK (cedula_juridica ~ '^[0-9]-[0-9]{3}-[0-9]{6}$')"); // Formato de cédula jurídica
    }

    public function down(): void
    {
        Schema::dropIfExists('comercios');
    }
};
