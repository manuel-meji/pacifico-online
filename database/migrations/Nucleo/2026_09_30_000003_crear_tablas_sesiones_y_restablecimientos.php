<?php

// Núcleo compartido (Comité de Arquitectura e Integración)
// Tablas técnicas de Laravel con nombre propio de la plataforma (PC3, sección 5.6).
// sesiones_usuario reemplaza a sessions (config/session.php, SESSION_TABLE).
// restablecimientos_contrasena reemplaza a password_reset_tokens (config/auth.php).
// Las columnas son las que define Laravel, para que el framework las use sin cambios.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sesiones activas del mecanismo de 5.1 (sesión de Laravel con Sanctum en modo SPA)
        Schema::create('sesiones_usuario', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index()
                ->constrained('usuarios', 'id_usuario', 'fk_sesiones_usuario_user_id')
                ->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Invitación del Encargado del comercio para definir su contraseña y recuperación de contraseña
        Schema::create('restablecimientos_contrasena', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restablecimientos_contrasena');
        Schema::dropIfExists('sesiones_usuario');
    }
};
