<?php

// Núcleo compartido (Comité de Arquitectura e Integración)
// Tabla usuarios: cuentas de acceso de los tres roles (PC3, secciones 5.1, 5.2 y 5.6).
// Solo el núcleo escribe en ella, mediante ServicioIdentidad::crearCuenta().
// La llave foránea de id_comercio hacia comercios (M2) se agrega en una migración
// posterior, porque la tabla comercios se crea después de esta.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id('id_usuario');
            $table->string('nombre', 150);
            $table->string('correo', 150)->unique('uq_usuarios_correo');
            $table->string('contrasena'); // Hash Bcrypt (ESC-07), nunca texto legible
            $table->string('rol', 30);
            $table->unsignedBigInteger('id_comercio')->nullable()->index('ix_usuarios_id_comercio');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Las restricciones CHECK solo se crean en PostgreSQL (Supabase).
        // SQLite, que usan las pruebas, no admite ALTER TABLE ... ADD CONSTRAINT.
        if (DB::getDriverName() === 'pgsql') {
            // Tres roles válidos (5.2)
            DB::statement("ALTER TABLE usuarios ADD CONSTRAINT chk_usuarios_rol
                           CHECK (rol IN ('encargado_plataforma', 'encargado_comercio', 'cliente'))");

            // id_comercio obligatorio para el Encargado del comercio y nulo para los demás roles (5.2 y 5.6)
            DB::statement("ALTER TABLE usuarios ADD CONSTRAINT chk_usuarios_comercio_por_rol
                           CHECK (
                               (rol = 'encargado_comercio' AND id_comercio IS NOT NULL)
                               OR (rol <> 'encargado_comercio' AND id_comercio IS NULL)
                           )");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
