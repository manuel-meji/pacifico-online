<?php

// M1 Portal y Búsqueda
// Llaves foráneas de M1 hacia M2 Comercios (tabla comercios, creada en
// M2_Comercios/2026_10_21_100000_create_comercios_table.php). Sección 6.5.
// Va aparte porque comercios se crea después de las tablas de M1.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comercios_destacados', function (Blueprint $table) {
            $table->foreign('id_comercio', 'fk_comercios_destacados_comercios')
                ->references('id_comercio')->on('comercios');
        });

        Schema::table('promociones_portal', function (Blueprint $table) {
            $table->foreign('id_comercio', 'fk_promociones_portal_comercios')
                ->references('id_comercio')->on('comercios');
        });
    }

    public function down(): void
    {
        Schema::table('promociones_portal', function (Blueprint $table) {
            $table->dropForeign('fk_promociones_portal_comercios');
        });

        Schema::table('comercios_destacados', function (Blueprint $table) {
            $table->dropForeign('fk_comercios_destacados_comercios');
        });
    }
};
