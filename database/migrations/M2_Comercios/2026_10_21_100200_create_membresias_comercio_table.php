<?php

// M2 — Micrositios y administración de comercios (SG2)
// Membresías asignadas a cada comercio (RN-008).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membresias_comercio', function (Blueprint $table) {
            $table->id('id_membresia_comercio');
            $table->unsignedBigInteger('id_comercio');
            $table->string('nombre', 100);
            $table->text('beneficios')->nullable();
            $table->decimal('comision_preferencial', 5, 2)->nullable();
            $table->date('inicio');
            $table->date('fin')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->foreign('id_comercio', 'fk_membresias_comercio_id_comercio')->references('id_comercio')->on('comercios')->restrictOnDelete();
        });

        // Restricciones que implementan reglas de negocio (sección 6.3)
        DB::statement("ALTER TABLE membresias_comercio ADD CONSTRAINT chk_membresia_comision_preferencial CHECK (comision_preferencial IS NULL OR (comision_preferencial >= 0 AND comision_preferencial <= 100))"); // RN-008
        DB::statement("ALTER TABLE membresias_comercio ADD CONSTRAINT chk_membresias_fechas CHECK (fin IS NULL OR fin >= inicio)"); // Vigencia coherente
        DB::statement("CREATE UNIQUE INDEX uq_membresia_activa_comercio ON membresias_comercio (id_comercio) WHERE activa");
    }

    public function down(): void
    {
        Schema::dropIfExists('membresias_comercio');
    }
};
