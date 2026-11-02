<?php

// Núcleo compartido (Comité de Arquitectura e Integración)
// Tabla bitacora_auditoria: registro de operaciones sensibles (PC3, secciones 5.4 y 5.6).
// Ningún módulo escribe directamente en ella; se usa BitacoraAuditoria::registrar().
// El trigger que rechaza UPDATE y DELETE va en una migración aparte (persona 7).
// La llave foránea de id_comercio hacia comercios (M2) se agrega en una migración posterior.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_auditoria', function (Blueprint $table) {
            $table->id('id_bitacora');
            $table->foreignId('id_usuario')->nullable() // Nulo en el contexto de sistema
                ->constrained('usuarios', 'id_usuario', 'fk_bitacora_auditoria_id_usuario')
                ->restrictOnDelete();
            $table->unsignedBigInteger('id_comercio')->nullable();
            $table->timestamp('fecha_hora');
            $table->string('accion', 100);
            $table->string('modulo', 10);
            $table->string('entidad', 60);
            $table->unsignedBigInteger('entidad_id');
            $table->string('resultado', 10);
            $table->string('motivo', 255)->nullable();

            // Índices de la sección 5.6
            $table->index(['id_comercio', 'fecha_hora'], 'ix_bitacora_comercio_fecha');
            $table->index(['entidad', 'entidad_id'], 'ix_bitacora_entidad');
        });

        if (DB::getDriverName() === 'pgsql') {
            // Resultado exitoso o fallido (5.4)
            DB::statement("ALTER TABLE bitacora_auditoria ADD CONSTRAINT chk_bitacora_resultado
                           CHECK (resultado IN ('exitoso', 'fallido'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_auditoria');
    }
};
