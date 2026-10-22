<?php

// M2 — Micrositios y administración de comercios (SG2)
// Medios de contacto y redes sociales del comercio.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacto_comercio', function (Blueprint $table) {
            $table->id('id_contacto');
            $table->unsignedBigInteger('id_comercio');
            $table->string('tipo', 20);
            $table->string('valor', 255);
            $table->timestamps();
            $table->foreign('id_comercio', 'fk_contacto_comercio_id_comercio')->references('id_comercio')->on('comercios')->restrictOnDelete();
        });

        // Restricciones que implementan reglas de negocio (sección 6.3)
        DB::statement("ALTER TABLE contacto_comercio ADD CONSTRAINT chk_contacto_tipo CHECK (tipo IN ('telefono', 'correo', 'direccion', 'facebook', 'instagram', 'whatsapp', 'tiktok', 'sitio_web'))"); // Tipos de contacto
    }

    public function down(): void
    {
        Schema::dropIfExists('contacto_comercio');
    }
};
