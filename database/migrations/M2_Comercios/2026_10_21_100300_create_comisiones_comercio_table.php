<?php

// M2 — Micrositios y administración de comercios (SG2)
// Configuración e historial del esquema de comisión de cada comercio (RN-006, RN-007, RN-008, RN-015, RN-017).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comisiones_comercio', function (Blueprint $table) {
            $table->id('id_comision_comercio');
            $table->unsignedBigInteger('id_comercio');
            $table->unsignedBigInteger('id_membresia_comercio')->nullable();
            $table->string('tipo', 15);
            $table->decimal('valor', 12, 2);
            $table->char('moneda', 3)->nullable();
            $table->timestamp('vigente_desde')->useCurrent();
            $table->timestamps();
            $table->unique(['id_comercio', 'vigente_desde'], 'uq_comision_comercio_vigencia');
            $table->foreign('id_comercio', 'fk_comisiones_comercio_id_comercio')->references('id_comercio')->on('comercios')->restrictOnDelete();
            $table->foreign('id_membresia_comercio', 'fk_comision_membresia')->references('id_membresia_comercio')->on('membresias_comercio')->restrictOnDelete();
        });

        // Restricciones que implementan reglas de negocio (sección 6.3)
        DB::statement("ALTER TABLE comisiones_comercio ADD CONSTRAINT chk_comisiones_tipo CHECK (tipo IN ('porcentaje', 'monto_fijo'))"); // RN-007
        DB::statement("ALTER TABLE comisiones_comercio ADD CONSTRAINT chk_comisiones_valor CHECK (valor >= 0 AND (tipo <> 'porcentaje' OR valor <= 100))"); // RN-006
        DB::statement("ALTER TABLE comisiones_comercio ADD CONSTRAINT chk_comisiones_moneda CHECK (tipo <> 'monto_fijo' OR moneda IS NOT NULL)"); // Monto fijo requiere moneda
    }

    public function down(): void
    {
        Schema::dropIfExists('comisiones_comercio');
    }
};
