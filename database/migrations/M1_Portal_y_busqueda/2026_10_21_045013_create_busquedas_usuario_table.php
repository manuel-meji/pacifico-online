<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Portal público — Historial de búsquedas (para ofrecer a M3 los términos más buscados).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('busquedas_usuario', function (Blueprint $table) {
            $table->id('id_busqueda_usuario');
            $table->foreignId('id_cliente')->nullable()
                  ->constrained('clientes', 'id_cliente')
                  ->nullOnDelete();                       // si se elimina el cliente, la búsqueda queda anónima
            $table->string('identificador_sesion', 100)->nullable();
            $table->string('termino_busqueda', 200);
            $table->integer('cantidad_resultados');
            $table->timestamp('fecha_busqueda')->useCurrent();
        });

        DB::statement('ALTER TABLE busquedas_usuario ADD CONSTRAINT chk_busquedas_usuario_resultados
                       CHECK (cantidad_resultados >= 0)');
        DB::statement("ALTER TABLE busquedas_usuario ADD CONSTRAINT chk_busquedas_usuario_termino
                       CHECK (btrim(termino_busqueda) <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('busquedas_usuario');
    }
};