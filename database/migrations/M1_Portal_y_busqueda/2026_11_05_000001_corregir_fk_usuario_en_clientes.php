<?php

// M1 Portal y Búsqueda
// Corrige la llave foránea clientes.id_usuario: apuntaba a users.id (tabla por defecto
// de Laravel, sin uso en el sistema) y debe apuntar a usuarios.id_usuario (núcleo),
// donde ServicioIdentidad::crearCuenta() crea las cuentas.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign('clientes_id_usuario_foreign');

            $table->foreign('id_usuario', 'fk_clientes_id_usuario')
                ->references('id_usuario')->on('usuarios')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign('fk_clientes_id_usuario');

            $table->foreign('id_usuario', 'clientes_id_usuario_foreign')
                ->references('id')->on('users');
        });
    }
};
