<?php

// M1 Portal y Búsqueda
// Llaves foráneas de M1 hacia M3 Catálogo (tablas productos y categorias, creadas en
// M3_Catalogo/2026_10_20_000003_create_productos_table.php y
// M3_Catalogo/2026_10_20_000001_create_categorias_table.php). Sección 6.5.
// Va aparte de las migraciones create de M1 para declarar en un solo lugar
// la dependencia de M1 con M3.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos_destacados', function (Blueprint $table) {
            $table->foreign('id_producto', 'fk_productos_destacados_productos')
                ->references('id_producto')->on('productos');
        });

        Schema::table('categorias_portal', function (Blueprint $table) {
            $table->foreign('id_categoria', 'fk_categorias_portal_categorias')
                ->references('id_categoria')->on('categorias');
        });

        Schema::table('promociones_portal', function (Blueprint $table) {
            $table->foreign('id_producto', 'fk_promociones_portal_productos')
                ->references('id_producto')->on('productos');

            $table->foreign('id_categoria', 'fk_promociones_portal_categorias')
                ->references('id_categoria')->on('categorias');
        });
    }

    public function down(): void
    {
        Schema::table('promociones_portal', function (Blueprint $table) {
            $table->dropForeign('fk_promociones_portal_categorias');
            $table->dropForeign('fk_promociones_portal_productos');
        });

        Schema::table('categorias_portal', function (Blueprint $table) {
            $table->dropForeign('fk_categorias_portal_categorias');
        });

        Schema::table('productos_destacados', function (Blueprint $table) {
            $table->dropForeign('fk_productos_destacados_productos');
        });
    }
};
