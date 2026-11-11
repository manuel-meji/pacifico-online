<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_procesados', function (Blueprint $table) {
            $table->id('id_evento_procesado');

            $table->uuid('evento_id');
            $table->string('tipo_evento', 80);

            $table->string('estado', 20)->default('PENDIENTE');

            $table->timestamp('fecha_recepcion')->useCurrent();
            $table->timestamp('fecha_procesamiento')->nullable();

            $table->text('ultimo_error')->nullable();

            $table->timestamps();

            // Principal mecanismo de idempotencia.
            $table->unique(
                'evento_id',
                'uq_eventos_procesados_evento_id'
            );
        });

        DB::statement("
            ALTER TABLE eventos_procesados
            ADD CONSTRAINT chk_eventos_procesados_estado
            CHECK (estado IN ('PENDIENTE', 'PROCESADO', 'FALLIDO'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_procesados');
    }
};