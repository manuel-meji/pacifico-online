<?php

declare(strict_types=1);

namespace Tests\Feature\Nucleo;

use App\Enums\Nucleo\RolUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrearEncargadoPlataformaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Solo se cargan las migraciones necesarias para probar el comando.
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

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * El comando obtiene estos datos mediante env().
         * Se establecen aquí para que cada prueba sea reproducible.
         */
        putenv('NUCLEO_ENCARGADO_NOMBRE=Administrador Plataforma');
        putenv('NUCLEO_ENCARGADO_CORREO=admin@pacifico.test');
        putenv('NUCLEO_ENCARGADO_CONTRASENA=secreta123');

        $_ENV['NUCLEO_ENCARGADO_NOMBRE'] = 'Administrador Plataforma';
        $_ENV['NUCLEO_ENCARGADO_CORREO'] = 'admin@pacifico.test';
        $_ENV['NUCLEO_ENCARGADO_CONTRASENA'] = 'secreta123';
    }

    protected function tearDown(): void
    {
        putenv('NUCLEO_ENCARGADO_NOMBRE');
        putenv('NUCLEO_ENCARGADO_CORREO');
        putenv('NUCLEO_ENCARGADO_CONTRASENA');

        unset(
            $_ENV['NUCLEO_ENCARGADO_NOMBRE'],
            $_ENV['NUCLEO_ENCARGADO_CORREO'],
            $_ENV['NUCLEO_ENCARGADO_CONTRASENA'],
        );

        parent::tearDown();
    }

    public function test_crea_el_primer_encargado_de_plataforma(): void
    {
        $this->artisan('nucleo:crear-encargado-plataforma')
            ->expectsOutput('Encargado de la plataforma creado.')
            ->assertSuccessful();

        $this->assertDatabaseHas('usuarios', [
            'nombre' => 'Administrador Plataforma',
            'correo' => 'admin@pacifico.test',
            'rol' => RolUsuario::EncargadoPlataforma->value,
            'id_comercio' => null,
            'activo' => true,
        ]);
    }

    public function test_la_contrasena_del_encargado_se_guarda_cifrada(): void
    {
        $this->artisan('nucleo:crear-encargado-plataforma')
            ->assertSuccessful();

        $usuario = DB::table('usuarios')
            ->where('correo', 'admin@pacifico.test')
            ->first();

        $this->assertNotNull($usuario);
        $this->assertNotSame('secreta123', $usuario->contrasena);
        $this->assertTrue(
            Hash::check('secreta123', $usuario->contrasena)
        );
    }

    public function test_registra_la_creacion_en_la_bitacora(): void
    {
        $this->artisan('nucleo:crear-encargado-plataforma')
            ->assertSuccessful();

        $usuario = DB::table('usuarios')
            ->where('correo', 'admin@pacifico.test')
            ->first();

        $this->assertNotNull($usuario);

        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'encargado_plataforma_creado',
            'modulo' => 'NUCLEO',
            'entidad' => 'usuarios',
            'entidad_id' => $usuario->id_usuario,
            'resultado' => 'exitoso',
        ]);
    }

    public function test_no_permite_crear_un_segundo_encargado_activo(): void
    {
        $this->artisan('nucleo:crear-encargado-plataforma')
            ->assertSuccessful();

        $this->artisan('nucleo:crear-encargado-plataforma')
            ->expectsOutput(
                'Ya existe un Encargado de la plataforma activo. No se crea otro.'
            )
            ->assertFailed();

        $cantidad = DB::table('usuarios')
            ->where(
                'rol',
                RolUsuario::EncargadoPlataforma->value
            )
            ->where('activo', true)
            ->count();

        $this->assertSame(1, $cantidad);
    }
}