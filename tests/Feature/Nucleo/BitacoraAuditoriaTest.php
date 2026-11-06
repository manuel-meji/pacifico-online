<?php

declare(strict_types=1);

namespace Tests\Feature\Nucleo;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Models\Nucleo\RegistroAuditoria;
use App\Models\Nucleo\Usuario;
use App\Services\Nucleo\BitacoraAuditoria;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BitacoraAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Se comparte el esquema independiente del núcleo con las demás pruebas.
     *
     * No se incluye la migración de FK hacia comercios porque esa tabla
     * pertenece a M2 y esta prueba debe poder ejecutarse de forma aislada.
     *
     * @var list<string>
     */
    private const MIGRACIONES = [
        'database/migrations/Nucleo/2026_09_30_000001_crear_tabla_usuarios.php',
        'database/migrations/Nucleo/2026_09_30_000002_crear_tabla_bitacora_auditoria.php',
        'database/migrations/Nucleo/2026_09_30_000003_crear_tablas_sesiones_y_restablecimientos.php',
        'database/migrations/Nucleo/2026_09_30_000004_crear_tablas_trabajos_cola.php',
        'database/migrations/Nucleo/2026_11_02_191532_agregar_inmutabilidad_a_bitacora_auditoria.php',
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

    public function test_servicio_y_bitacora_comparten_el_contexto_de_comercio(): void
    {
        $nucleo = $this->app->make(NucleoServiceInterface::class);
        $bitacora = $this->servicio();
        $this->app->make(ContextoComercio::class)->establecer(7);
        session(['comercio_id' => 99]);
        $this->actingAs(new Usuario(['id_comercio' => 88, 'activo' => true]));

        $bitacora->registrar('prueba_contexto', 'pruebas', 1);
        $nucleo->registrarAuditoria('prueba_contrato', 'pruebas', 2);

        $this->assertSame(7, $nucleo->getComercioActualId());
        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'prueba_contexto',
            'id_comercio' => 7,
        ]);
        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'prueba_contrato',
            'id_comercio' => 7,
        ]);
    }

    public function test_sin_contexto_no_toma_el_comercio_del_usuario_ni_de_la_sesion(): void
    {
        session(['comercio_id' => 99]);
        $this->actingAs(new Usuario(['id_comercio' => 88, 'activo' => true]));
        $nucleo = $this->app->make(NucleoServiceInterface::class);

        $nucleo->registrarAuditoria('sin_contexto', 'pruebas', 1);

        $this->assertNull($nucleo->getComercioActualId());
        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'sin_contexto',
            'id_comercio' => null,
        ]);
    }

    public function test_el_modelo_permite_insertar_y_leer_un_registro(): void
    {
        $registro = RegistroAuditoria::factory()->create();

        $this->assertModelExists($registro);
        $this->assertSame('registro_creado', $registro->fresh()->accion);
    }

    #[DataProvider('operacionesProhibidas')]
    public function test_el_modelo_rechaza_modificaciones_y_eliminaciones(string $operacion): void
    {
        $registro = RegistroAuditoria::factory()->create();
        $registro->accion = 'registro_modificado';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('La bitácora de auditoría es inmutable: UPDATE y DELETE no están permitidos.');

        try {
            $registro->{$operacion}();
        } finally {
            $this->assertDatabaseCount('bitacora_auditoria', 1);
            $this->assertDatabaseHas('bitacora_auditoria', [
                'id_bitacora' => $registro->id_bitacora,
                'accion' => 'registro_creado',
            ]);
        }
    }

    /** @return array<string, array{string}> */
    public static function operacionesProhibidas(): array
    {
        return [
            'guardar cambios' => ['save'],
            'guardar sin eventos' => ['saveQuietly'],
            'actualizar' => ['update'],
            'actualizar sin eventos' => ['updateQuietly'],
            'eliminar' => ['delete'],
            'eliminar sin eventos' => ['deleteQuietly'],
            'forzar eliminacion' => ['forceDelete'],
        ];
    }

    public function test_postgresql_rechaza_update_directo_en_bitacora(): void
{
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped(
            'Esta prueba requiere PostgreSQL porque valida el trigger PL/pgSQL.'
        );
    }

    $registro = RegistroAuditoria::factory()->create();

    $this->expectException(\Throwable::class);

    DB::table('bitacora_auditoria')
        ->where('id_bitacora', $registro->id_bitacora)
        ->update([
            'accion' => 'modificacion_no_permitida',
        ]);
}

public function test_postgresql_rechaza_delete_directo_en_bitacora(): void
{
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped(
            'Esta prueba requiere PostgreSQL porque valida el trigger PL/pgSQL.'
        );
    }

    $registro = RegistroAuditoria::factory()->create();

    $this->expectException(\Throwable::class);

    DB::table('bitacora_auditoria')
        ->where('id_bitacora', $registro->id_bitacora)
        ->delete();
}
}
