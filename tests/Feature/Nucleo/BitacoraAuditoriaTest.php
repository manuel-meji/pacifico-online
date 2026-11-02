<?php

declare(strict_types=1);

namespace Tests\Feature\Nucleo;

use App\Services\Nucleo\BitacoraAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BitacoraAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Solo cargamos las migraciones necesarias para estas pruebas.
     *
     * No se incluye la migración de FK hacia comercios porque esa tabla
     * pertenece a M2 y esta prueba debe poder ejecutarse de forma aislada.
     *
     * @var list<string>
     */
    private const MIGRACIONES = [
        'database/migrations/Nucleo/2026_09_30_000001_crear_tabla_usuarios.php',
        'database/migrations/Nucleo/2026_09_30_000002_crear_tabla_bitacora_auditoria.php',
    ];

    protected function migrateUsing(): array
    {
        return [
            '--path' => self::MIGRACIONES,
            '--seed' => false,
        ];
    }

    protected function migrateFreshUsing(): array
    {
        return [
            '--path' => self::MIGRACIONES,
            '--drop-views' => false,
            '--drop-types' => false,
            '--seed' => false,
        ];
    }

    private function servicio(): BitacoraAuditoria
    {
        return $this->app->make(BitacoraAuditoria::class);
    }

    public function test_registra_una_operacion_en_la_bitacora(): void
    {
        $this->servicio()->registrar(
            'prueba_creada',
            'pruebas',
            1,
            [
                'modulo' => 'NUCLEO',
                'resultado' => 'exitoso',
            ]
        );

        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'prueba_creada',
            'modulo' => 'NUCLEO',
            'entidad' => 'pruebas',
            'entidad_id' => 1,
            'resultado' => 'exitoso',
        ]);
    }

    public function test_permite_registro_del_sistema_sin_usuario(): void
    {
        $this->servicio()->registrar(
            'proceso_sistema',
            'procesos',
            10,
            [
                'modulo' => 'NUCLEO',
            ]
        );

        $registro = DB::table('bitacora_auditoria')->first();

        $this->assertNotNull($registro);
        $this->assertNull($registro->id_usuario);
    }

    public function test_registra_operacion_fallida_y_su_motivo(): void
    {
        $this->servicio()->registrar(
            'acceso_denegado',
            'usuarios',
            2,
            [
                'modulo' => 'NUCLEO',
                'resultado' => 'fallido',
                'motivo' => 'Rol sin permiso',
            ]
        );

        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'acceso_denegado',
            'resultado' => 'fallido',
            'motivo' => 'Rol sin permiso',
        ]);
    }

    public function test_registra_el_modulo_indicado(): void
    {
        $this->servicio()->registrar(
            'reserva_creada',
            'reservas',
            5,
            [
                'modulo' => 'M4',
            ]
        );

        $this->assertDatabaseHas('bitacora_auditoria', [
            'modulo' => 'M4',
            'accion' => 'reserva_creada',
        ]);
    }

    /*
     * TODO POSTGRESQL:
     *
     * La prueba que verifica que UPDATE y DELETE son rechazados no debe
     * ejecutarse con SQLite, porque el trigger utiliza PL/pgSQL.
     *
     * Esta prueba debe agregarse cuando Supabase/PostgreSQL vuelva a estar
     * disponible o usando PostgreSQL local.
     */
}