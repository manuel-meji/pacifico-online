<?php

declare(strict_types=1);

namespace Tests\Feature\Nucleo;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Models\Nucleo\Usuario;
use App\Services\Nucleo\ContextoComercio;
use Database\Seeders\M2_Comercios\ComerciosDemoSeeder;
use Database\Seeders\Nucleo\CuentasDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

/**
 * CuentasDemoSeeder: las seis cuentas del escenario de demostración (colectivo del PC4, 2.2).
 */
class CuentasDemoSeederTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refrescarBaseDeLaravel;
    }

    private const CONTRASENA = 'clave-de-demostracion-123';

    /**
     * Estas pruebas agregan las tablas de M2 a las del núcleo. RefreshDatabase migra una
     * sola vez por proceso, así que se parte de una base nueva y al terminar se deja como
     * "sin migrar", para no cambiarle las tablas a las demás pruebas (igual que PreparaM2).
     */
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

    /**
     * Las migraciones de las demás pruebas del núcleo, más las de M2 que usa ComerciosDemoSeeder.
     *
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        return ['--path' => [
            'database/migrations/Nucleo/2026_09_30_000001_crear_tabla_usuarios.php',
            'database/migrations/Nucleo/2026_09_30_000002_crear_tabla_bitacora_auditoria.php',
            'database/migrations/Nucleo/2026_09_30_000003_crear_tablas_sesiones_y_restablecimientos.php',
            'database/migrations/Nucleo/2026_09_30_000004_crear_tablas_trabajos_cola.php',
            'database/migrations/Nucleo/2026_11_02_191532_agregar_inmutabilidad_a_bitacora_auditoria.php',
            'database/migrations/M2_Comercios',
        ], '--drop-views' => false, '--drop-types' => false, '--seed' => false];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['nucleo.demo.contrasena' => self::CONTRASENA]);
    }

    public function test_crea_las_seis_cuentas_con_su_rol_y_el_comercio_de_su_cedula(): void
    {
        $this->seed(ComerciosDemoSeeder::class);
        $this->seed(CuentasDemoSeeder::class);

        $idPorCedula = $this->idsDeComercioPorCedula();
        $this->assertCount(5, $idPorCedula);
        $this->assertSame(6, Usuario::query()->count());

        foreach (CuentasDemoSeeder::CUENTAS as $cuenta) {
            $usuario = Usuario::query()->where('correo', $cuenta['correo'])->firstOrFail();

            $this->assertSame($cuenta['nombre'], $usuario->nombre);
            $this->assertSame($cuenta['rol'], $usuario->rol);
            $this->assertTrue($usuario->activo);
            $this->assertSame(
                $cuenta['cedula'] === null ? null : $idPorCedula[$cuenta['cedula']],
                $usuario->id_comercio,
                "id_comercio de {$cuenta['correo']}",
            );
        }

        $plataforma = Usuario::query()->where('correo', 'plataforma.m2@prueba.cr')->firstOrFail();
        $this->assertSame(RolUsuario::EncargadoPlataforma, $plataforma->rol);
        $this->assertNull($plataforma->id_comercio);
        $this->assertSame(5, Usuario::query()->where('rol', RolUsuario::EncargadoComercio)->distinct()->count('id_comercio'));
    }

    public function test_la_contrasena_sirve_para_iniciar_sesion(): void
    {
        $this->seed(ComerciosDemoSeeder::class);
        $this->seed(CuentasDemoSeeder::class);

        $usuario = Usuario::query()->where('correo', 'encargado4.m2@prueba.cr')->firstOrFail();

        // Se guarda cifrada una sola vez: el hash corresponde a la contraseña en texto plano.
        $this->assertNotSame(self::CONTRASENA, $usuario->contrasena);
        $this->assertTrue(Hash::check(self::CONTRASENA, $usuario->contrasena));

        $this->postJson('/login', ['correo' => 'encargado4.m2@prueba.cr', 'contrasena' => self::CONTRASENA])
            ->assertOk()
            ->assertJsonPath('user.rol', 'encargado_comercio')
            ->assertJsonPath('user.id_comercio', $usuario->id_comercio);

        $this->assertAuthenticatedAs($usuario, 'web');
    }

    public function test_el_encargado_de_la_plataforma_tambien_inicia_sesion(): void
    {
        $this->seed(ComerciosDemoSeeder::class);
        $this->seed(CuentasDemoSeeder::class);

        $this->postJson('/login', ['correo' => 'plataforma.m2@prueba.cr', 'contrasena' => self::CONTRASENA])
            ->assertOk()
            ->assertJsonPath('user.rol', 'encargado_plataforma')
            ->assertJsonPath('user.id_comercio', null);
    }

    public function test_ejecutarlo_dos_veces_no_duplica_cuentas(): void
    {
        $this->seed(ComerciosDemoSeeder::class);
        $this->seed(CuentasDemoSeeder::class);
        $antes = Usuario::query()->orderBy('id_usuario')->get(['id_usuario', 'correo', 'rol', 'id_comercio'])->toArray();

        $this->seed(CuentasDemoSeeder::class);

        $this->assertSame(6, Usuario::query()->count());
        $this->assertSame($antes, Usuario::query()->orderBy('id_usuario')->get(['id_usuario', 'correo', 'rol', 'id_comercio'])->toArray());
        $this->assertTrue(Hash::check(
            self::CONTRASENA,
            Usuario::query()->where('correo', 'encargado1.m2@prueba.cr')->firstOrFail()->contrasena,
        ));
    }

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['   '])]
    public function test_sin_demo_contrasena_lanza_la_excepcion_y_no_crea_nada(?string $valor): void
    {
        $this->seed(ComerciosDemoSeeder::class);
        config(['nucleo.demo.contrasena' => $valor]);

        try {
            $this->seed(CuentasDemoSeeder::class);
            $this->fail('Se esperaba una RuntimeException por la falta de DEMO_CONTRASENA.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('DEMO_CONTRASENA', $e->getMessage());
        }

        $this->assertSame(0, Usuario::query()->count());
    }

    public function test_sin_los_comercios_de_m2_lanza_la_excepcion_y_no_crea_nada(): void
    {
        try {
            $this->seed(CuentasDemoSeeder::class);
            $this->fail('Se esperaba una RuntimeException por la falta de los comercios de M2.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ComerciosDemoSeeder', $e->getMessage());

            foreach (['3-101-100001', '3-101-100002', '3-101-100003', '3-101-100004', '3-101-100005'] as $cedula) {
                $this->assertStringContainsString($cedula, $e->getMessage());
            }
        }

        // Tampoco queda la cuenta de plataforma, que no depende de ningún comercio.
        $this->assertSame(0, Usuario::query()->count());
    }

    /**
     * Lo que publica M2 por su contrato, sin leer sus tablas.
     *
     * @return array<string, int>
     */
    private function idsDeComercioPorCedula(): array
    {
        $comercios = app(ComerciosServiceInterface::class);

        return app(ContextoComercio::class)->ejecutarComoSistema(function () use ($comercios): array {
            $idPorCedula = [];

            foreach ($comercios->listarComerciosHabilitados(1, 50)['comercios'] as $comercio) {
                $fiscales = $comercios->obtenerDatosFiscalesComercio($comercio['id_comercio']);
                $idPorCedula[$fiscales['cedula_juridica']] = $fiscales['id_comercio'];
            }

            return $idPorCedula;
        });
    }
}
