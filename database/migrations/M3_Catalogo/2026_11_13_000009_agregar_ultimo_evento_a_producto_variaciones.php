<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3 Catálogo. Último evento de existencia (M4) aplicado a cada variación.
 *
 * ExistenciaAgotada y ExistenciaRepuesta pueden llegar desordenados. Con estas dos
 * columnas el listener descarta un evento anterior al último aplicado y reconoce un
 * evento repetido, igual que estado_comercios_catalogo con los eventos de M2.
 * Ambas son opcionales: una variación sin eventos las tiene en NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_variaciones', function (Blueprint $table) {
            $table->timestampTz('fecha_ultimo_evento')->nullable();
            $table->uuid('id_ultimo_evento')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('producto_variaciones', function (Blueprint $table) {
            $table->dropColumn(['fecha_ultimo_evento', 'id_ultimo_evento']);
        });
    }
};
