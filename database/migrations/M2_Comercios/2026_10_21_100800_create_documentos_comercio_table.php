<?php

// M2 — Micrositios y administración de comercios (SG2)
// Documentos del comercio y su evaluación (RN-002, CU-0022).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_comercio', function (Blueprint $table) {
            $table->id('id_documento_comercio');
            $table->unsignedBigInteger('id_comercio');
            $table->string('tipo', 50);
            $table->string('ruta_cifrada', 255);
            $table->date('fecha_vencimiento')->nullable();
            $table->string('resultado', 15)->default('pendiente');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->foreign('id_comercio', 'fk_documentos_comercio_id_comercio')->references('id_comercio')->on('comercios')->restrictOnDelete();
        });

        // Restricciones que implementan reglas de negocio (sección 6.3)
        DB::statement("ALTER TABLE documentos_comercio ADD CONSTRAINT chk_documentos_resultado CHECK (resultado IN ('pendiente', 'valido', 'invalido'))"); // RN-002
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_comercio');
    }
};
