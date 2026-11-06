<?php

// M1 Portal y Búsqueda
// Llaves foráneas de M1 hacia el núcleo (tabla usuarios, creada en
// Nucleo/2026_09_30_000001_crear_tabla_usuarios.php). Sección 6.5.
// Va aparte de las migraciones create de M1 para declarar en un solo lugar
// la dependencia de M1 con el núcleo.
//
// clientes.id_usuario ya tenía la llave fk_clientes_id_usuario (creada en
// 2026_11_05_000001_corregir_fk_usuario_en_clientes.php); aquí se reemplaza por
// fk_clientes_usuarios para usar el nombre de la sección 6.5. El down() la
// restaura para que el rollback de la migración anterior siga funcionando.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign('fk_clientes_id_usuario');

            $table->foreign('id_usuario', 'fk_clientes_usuarios')
                ->references('id_usuario')->on('usuarios');
        });

        Schema::table('noticias_portal', function (Blueprint $table) {
            $table->foreign('id_usuario_autor', 'fk_noticias_portal_usuarios')
                ->references('id_usuario')->on('usuarios');
        });
    }

    public function down(): void
    {
        Schema::table('noticias_portal', function (Blueprint $table) {
            $table->dropForeign('fk_noticias_portal_usuarios');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign('fk_clientes_usuarios');

            $table->foreign('id_usuario', 'fk_clientes_id_usuario')
                ->references('id_usuario')->on('usuarios')
                ->restrictOnDelete();
        });
    }
};
