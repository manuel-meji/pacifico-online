<?php

declare(strict_types=1);

namespace Tests\Feature\Nucleo;

use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\Nucleo\Usuario;
use App\Notifications\Nucleo\InvitacionEncargadoComercio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * ServicioIdentidad::crearCuenta (PC3, 5.1). Corre sobre SQLite en memoria.
 */
class ServicioIdentidadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Las migraciones independientes del núcleo compartidas con las demás pruebas. Así no depende de las migraciones
     * de otros módulos, que pueden traer SQL exclusivo de PostgreSQL y no corren en SQLite.
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

    /** @return array<string, mixed> */
    protected function migrateUsing(): array
    {
        return ['--path' => self::MIGRACIONES, '--seed' => false];
    }

    /** @return array<string, mixed> */
    protected function migrateFreshUsing(): array
    {
        return ['--path' => self::MIGRACIONES, '--drop-views' => false, '--drop-types' => false, '--seed' => false];
    }

    private function servicio(): ServicioIdentidadInterface
    {
        return $this->app->make(ServicioIdentidadInterface::class);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function datos(array $datos = []): array
    {
        return array_merge([
            'nombre' => 'Ana Mora',
            'correo' => 'ana@example.com',
            'contrasena' => 'secreta123',
        ], $datos);
    }

    /**
     * La tabla comercios es de M2. Si SQLite tiene la llave foránea de usuarios.id_comercio,
     * no se puede crear un encargado de comercio sin un comercio real; esas pruebas se omiten.
     */
    private function omitirSiHayLlaveForaneaDeComercio(): void
    {
        if (DB::getDriverName() === 'sqlite' && ! empty(DB::select('PRAGMA foreign_key_list(usuarios)'))) {
            $this->markTestSkipped('usuarios tiene llave foránea a comercios (M2): requiere un comercio real.');
        }
    }

    private function codigoDeError(callable $accion): ErrorNucleoException
    {
        try {
            $accion();
        } catch (ErrorNucleoException $e) {
            return $e;
        }

        $this->fail('Se esperaba una ErrorNucleoException.');
    }

    public function test_crea_cliente_con_contrasena_cifrada_y_sin_comercio(): void
    {
        $usuario = $this->servicio()->crearCuenta($this->datos(), RolUsuario::Cliente);

        $this->assertNotNull($usuario->id_usuario);
        $this->assertSame(RolUsuario::Cliente, $usuario->rol);
        $this->assertNull($usuario->id_comercio);
        $this->assertTrue($usuario->activo);
        $this->assertNotSame('secreta123', $usuario->contrasena);
        $this->assertTrue(Hash::check('secreta123', $usuario->contrasena));
    }

    public function test_acepta_el_rol_como_texto(): void
    {
        $usuario = $this->servicio()->crearCuenta($this->datos(), 'cliente');

        $this->assertSame(RolUsuario::Cliente, $usuario->rol);
    }

    public function test_normaliza_el_correo_a_minusculas(): void
    {
        $usuario = $this->servicio()->crearCuenta($this->datos(['correo' => '  ANA@Example.com ']), RolUsuario::Cliente);

        $this->assertSame('ana@example.com', $usuario->correo);
    }

    public function test_crea_encargado_de_plataforma_sin_comercio(): void
    {
        $usuario = $this->servicio()->crearCuenta($this->datos(), RolUsuario::EncargadoPlataforma);

        $this->assertSame(RolUsuario::EncargadoPlataforma, $usuario->rol);
        $this->assertNull($usuario->id_comercio);
    }

    public function test_crea_encargado_de_comercio_y_envia_invitacion(): void
    {
        Notification::fake();
        $this->omitirSiHayLlaveForaneaDeComercio();

        $usuario = $this->servicio()->crearCuenta(
            $this->datos(['contrasena' => null]),
            RolUsuario::EncargadoComercio,
            idComercio: 7,
        );

        $this->assertSame(RolUsuario::EncargadoComercio, $usuario->rol);
        $this->assertSame(7, $usuario->id_comercio);
        Notification::assertSentTo($usuario, InvitacionEncargadoComercio::class);
        $this->assertTrue(DB::table('restablecimientos_contrasena')->where('email', 'ana@example.com')->exists());
    }

    public function test_el_encargado_de_comercio_no_queda_con_una_contrasena_conocida(): void
    {
        Notification::fake();
        $this->omitirSiHayLlaveForaneaDeComercio();

        $usuario = $this->servicio()->crearCuenta(
            $this->datos(['contrasena' => 'secreta123']),
            RolUsuario::EncargadoComercio,
            idComercio: 7,
        );

        $this->assertFalse(Hash::check('secreta123', $usuario->contrasena));
    }

    public function test_encargado_de_comercio_sin_id_comercio_da_err_005(): void
    {
        $e = $this->codigoDeError(fn () => $this->servicio()->crearCuenta($this->datos(), RolUsuario::EncargadoComercio));

        $this->assertSame('ERR-005', $e->getCodigo());
        $this->assertSame(422, $e->getCodigoHttp());
        $this->assertArrayHasKey('id_comercio', $e->getDetalles());
    }

    public function test_cliente_con_id_comercio_da_err_005(): void
    {
        $e = $this->codigoDeError(fn () => $this->servicio()->crearCuenta($this->datos(), RolUsuario::Cliente, 7));

        $this->assertSame('ERR-005', $e->getCodigo());
        $this->assertArrayHasKey('id_comercio', $e->getDetalles());
    }

    public function test_encargado_de_plataforma_con_id_comercio_da_err_005(): void
    {
        $e = $this->codigoDeError(fn () => $this->servicio()->crearCuenta($this->datos(), RolUsuario::EncargadoPlataforma, 7));

        $this->assertSame('ERR-005', $e->getCodigo());
    }

    public function test_correo_repetido_da_err_005_sin_importar_mayusculas(): void
    {
        $this->servicio()->crearCuenta($this->datos(), RolUsuario::Cliente);

        $e = $this->codigoDeError(
            fn () => $this->servicio()->crearCuenta($this->datos(['correo' => 'ANA@example.com']), RolUsuario::Cliente)
        );

        $this->assertSame('ERR-005', $e->getCodigo());
        $this->assertArrayHasKey('correo', $e->getDetalles());
        $this->assertSame(1, Usuario::count());
    }

    public function test_rol_invalido_da_err_005(): void
    {
        $e = $this->codigoDeError(fn () => $this->servicio()->crearCuenta($this->datos(), 'administrador'));

        $this->assertSame('ERR-005', $e->getCodigo());
        $this->assertArrayHasKey('rol', $e->getDetalles());
    }

    public function test_cliente_sin_contrasena_o_con_contrasena_corta_da_err_005(): void
    {
        $sin = $this->codigoDeError(fn () => $this->servicio()->crearCuenta($this->datos(['contrasena' => null]), RolUsuario::Cliente));
        $corta = $this->codigoDeError(fn () => $this->servicio()->crearCuenta($this->datos(['contrasena' => '1234']), RolUsuario::Cliente));

        $this->assertArrayHasKey('contrasena', $sin->getDetalles());
        $this->assertArrayHasKey('contrasena', $corta->getDetalles());
    }

    public function test_correo_con_formato_invalido_da_err_005(): void
    {
        $e = $this->codigoDeError(fn () => $this->servicio()->crearCuenta($this->datos(['correo' => 'no-es-un-correo']), RolUsuario::Cliente));

        $this->assertArrayHasKey('correo', $e->getDetalles());
    }

    public function test_si_el_modulo_revierte_su_transaccion_la_cuenta_tambien_se_revierte(): void
    {
        // Flujo de registro de M1: cuenta y cliente se crean en una sola transacción.
        try {
            DB::transaction(function () {
                $this->servicio()->crearCuenta($this->datos(), RolUsuario::Cliente);

                throw new \RuntimeException('Falla al crear el registro en clientes.');
            });
        } catch (\RuntimeException) {
            // Se espera.
        }

        $this->assertSame(0, Usuario::count());
    }
}
