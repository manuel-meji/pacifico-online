<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La persona encargada crea su cuenta al enviar la solicitud (con su propia contraseña), para
 * ingresar y ver el estado de la revisión. cuenta_creada indica que la cuenta ya existe y que al
 * aprobar no hay que crearla; las solicitudes anteriores quedan en false y siguen con la invitación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_comercio', function (Blueprint $table) {
            $table->boolean('cuenta_creada')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_comercio', function (Blueprint $table) {
            $table->dropColumn('cuenta_creada');
        });
    }
};
