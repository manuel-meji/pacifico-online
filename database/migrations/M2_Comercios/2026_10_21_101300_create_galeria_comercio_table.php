<?php

// M2 — Micrositios y administración de comercios (SG2)
// Imágenes de la galería del micrositio (CU-0005).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galeria_comercio', function (Blueprint $table) {
            $table->id('id_imagen');
            $table->unsignedBigInteger('id_comercio');
            $table->string('ruta', 255);
            $table->string('texto_alternativo', 150);
            $table->smallInteger('orden')->default(1);
            $table->timestamps();
            $table->foreign('id_comercio', 'fk_galeria_comercio_id_comercio')->references('id_comercio')->on('comercios')->restrictOnDelete();
        });

        // Restricciones que implementan reglas de negocio (sección 6.3)
        DB::statement("ALTER TABLE galeria_comercio ADD CONSTRAINT chk_galeria_orden CHECK (orden >= 1)"); // Orden válido
    }

    public function down(): void
    {
        Schema::dropIfExists('galeria_comercio');
    }
};
