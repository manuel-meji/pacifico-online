<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios\Concerns;

use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\ComisionComercio;
use App\Models\Nucleo\Usuario;
use App\Services\Nucleo\ContextoComercio;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

/**
 * Base de datos de las pruebas de M2: SQLite en memoria (phpunit.xml) con solo
 * las migraciones de M2, igual que las pruebas del núcleo. Incluye fábricas
 * mínimas de comercios y usuarios.
 *
 * RefreshDatabase migra una sola vez por proceso y reutiliza esa base para todas
 * las clases, así que la primera clase decide qué tablas existen. Para no afectar
 * a otros módulos (ni depender de ellos), cada prueba de M2 parte de una base
 * nueva y al terminar deja el estado como "sin migrar".
 */
trait PreparaM2
{
    use RefreshDatabase {
        refreshDatabase as protected refrescarBaseDeLaravel;
    }

    public function refreshDatabase(): void
    {
        $this->olvidarBaseCompartida();
        $this->beforeApplicationDestroyed(fn () => $this->olvidarBaseCompartida());

        $this->refrescarBaseDeLaravel();
    }

    private function olvidarBaseCompartida(): void
    {
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
    }

    protected function migrateUsing(): array
    {
        return ['--path' => 'database/migrations/M2_Comercios', '--seed' => false];
    }

    protected function migrateFreshUsing(): array
    {
        return ['--path' => 'database/migrations/M2_Comercios', '--drop-views' => false, '--drop-types' => false, '--seed' => false];
    }

    /** @param  array<string, mixed>  $datos */
    protected function crearComercio(array $datos = []): Comercio
    {
        static $secuencia = 500000;
        $secuencia++;

        return Comercio::query()->create($datos + [
            'cedula_juridica' => '3-101-'.$secuencia,
            'nombre_comercial' => 'Comercio '.$secuencia,
            'razon_social' => 'Comercio '.$secuencia.' S.A.',
            'direccion_fiscal' => 'Liberia, Guanacaste',
            'inscrito_como_contribuyente' => true,
            'estado' => Comercio::ACTIVO,
        ]);
    }

    protected function crearComision(Comercio $comercio, string $tipo, float $valor, ?string $moneda = null, string $desde = '2026-11-01 00:00:00'): ComisionComercio
    {
        return ComisionComercio::query()->create([
            'id_comercio' => $comercio->id_comercio,
            'tipo' => $tipo,
            'valor' => $valor,
            'moneda' => $moneda,
            'vigente_desde' => $desde,
        ]);
    }

    /** Usuario del núcleo sin persistir (la tabla usuarios no se migra en estas pruebas). */
    protected function usuario(string $rol, ?int $idComercio = null, bool $activo = true): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Prueba', 'correo' => 'prueba@test.cr', 'rol' => $rol, 'id_comercio' => $idComercio, 'activo' => $activo]);
        $usuario->setAttribute($usuario->getKeyName(), 1);

        return $usuario;
    }

    /**
     * Ejecuta sin el filtro de PerteneceAComercio (modo sistema del núcleo). Sirve para
     * preparar datos de prueba de varios comercios o revisar lo que quedó guardado.
     */
    protected function comoSistema(Closure $operacion): mixed
    {
        return app(ContextoComercio::class)->ejecutarComoSistema($operacion);
    }

    /** Fija el ámbito del comercio, como hace EstablecerComercio con un encargado de comercio. */
    protected function enAmbitoDe(int $idComercio): void
    {
        app(ContextoComercio::class)->establecer($idComercio);
    }
}
