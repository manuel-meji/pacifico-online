<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comercios_destacados', function (Blueprint $table) {
            $table->id('id_comercio_destacado');
            $table->unsignedBigInteger('id_comercio');
            $table->smallInteger('posicion');
            $table->timestamp('fecha_inicio');
            $table->timestamp('fecha_fin');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        DB::statement('ALTER TABLE comercios_destacados ADD CONSTRAINT chk_comercios_destacados_posicion
                    CHECK (posicion > 0)');
        DB::statement('ALTER TABLE comercios_destacados ADD CONSTRAINT chk_comercios_destacados_vigencia
                    CHECK (fecha_fin > fecha_inicio)');
    }

    public function down(): void
    {
        Schema::dropIfExists('comercios_destacados');
    }
};
