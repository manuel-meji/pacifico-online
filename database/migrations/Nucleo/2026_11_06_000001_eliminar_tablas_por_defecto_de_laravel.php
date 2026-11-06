<?php

// Núcleo compartido (Comité de Arquitectura e Integración)
// Elimina las tablas por defecto de Laravel que el núcleo ya reemplazó (PC3, sección 5.6):
// users -> usuarios, sessions -> sesiones_usuario,
// password_reset_tokens -> restablecimientos_contrasena,
// jobs -> trabajos_cola y failed_jobs -> trabajos_fallidos.
// cache, cache_locks y job_batches se conservan porque siguen en uso.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas a eliminar, en el orden en que se eliminan.
     *
     * @var list<string>
     */
    private array $tablas = [
        'password_reset_tokens',
        'sessions',
        'failed_jobs',
        'jobs',
        'users',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->verificarQueNoHayLlavesForaneas();
        }

        foreach ($this->tablas as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }

    public function down(): void
    {
        // Mismas definiciones que 0001_01_01_000000_create_users_table.php
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Mismas definiciones que 0001_01_01_000002_create_jobs_table.php (sin job_batches)
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
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

    /**
     * Detiene la migración si alguna llave foránea todavía apunta a las tablas a eliminar.
     */
    private function verificarQueNoHayLlavesForaneas(): void
    {
        $marcadores = implode(', ', array_fill(0, count($this->tablas), '?'));

        $llaves = DB::select(
            "select origen.relname as tabla, llave.conname as llave, destino.relname as tabla_referida
            from pg_constraint llave
            join pg_class origen on origen.oid = llave.conrelid
            join pg_class destino on destino.oid = llave.confrelid
            join pg_namespace esquema on esquema.oid = destino.relnamespace
            where llave.contype = 'f'
              and esquema.nspname = current_schema()
              and destino.relname in ({$marcadores})
            order by origen.relname, llave.conname",
            $this->tablas
        );

        if ($llaves === []) {
            return;
        }

        $detalle = implode('; ', array_map(
            fn (object $llave): string => "{$llave->tabla}.{$llave->llave} -> {$llave->tabla_referida}",
            $llaves
        ));

        throw new RuntimeException(
            "No se eliminaron las tablas por defecto de Laravel: todavía hay llaves foráneas que apuntan a ellas ({$detalle})."
        );
    }
};
