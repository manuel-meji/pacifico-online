<?php

// Núcleo compartido (Comité de Arquitectura e Integración)
// Cola de eventos entre módulos (ADR-005) y trabajos que agotaron sus reintentos (PC3, 5.5 y 5.6).
// trabajos_cola reemplaza a jobs y trabajos_fallidos reemplaza a failed_jobs (config/queue.php).
// Las columnas son las que define Laravel, para que el framework las use sin cambios.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabajos_cola', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('trabajos_fallidos', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();

            $table->index(['connection', 'queue', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajos_fallidos');
        Schema::dropIfExists('trabajos_cola');
    }
};
