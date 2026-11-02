<?php

// Núcleo compartido (Comité de Arquitectura e Integración)
// Llaves foráneas de id_comercio hacia comercios (M2) en usuarios y bitacora_auditoria (PC3, 5.4 y 5.6).
// Va aparte porque comercios se crea después de las tablas del núcleo.
// El enunciado (5.4) permite declarar la llave; la lectura sigue siendo por contrato.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreign('id_comercio', 'fk_usuarios_id_comercio')
                ->references('id_comercio')->on('comercios')
                ->restrictOnDelete();
        });

        Schema::table('bitacora_auditoria', function (Blueprint $table) {
            $table->foreign('id_comercio', 'fk_bitacora_auditoria_id_comercio')
                ->references('id_comercio')->on('comercios')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bitacora_auditoria', function (Blueprint $table) {
            $table->dropForeign('fk_bitacora_auditoria_id_comercio');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign('fk_usuarios_id_comercio');
        });
    }
};
