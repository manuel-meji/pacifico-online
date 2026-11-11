<?php

declare(strict_types=1);

namespace Tests\Feature\Nucleo;

use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Models\Nucleo\Usuario;
use App\Notifications\Nucleo\InvitacionEncargadoComercio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * POST /crear-contrasena: la invitación del Encargado del comercio. Corre sobre SQLite en memoria.
 */
class CrearContrasenaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Superconjunto seguro de migraciones del núcleo: evita las de otros módulos (SQL de
     * PostgreSQL) sin dejar un esquema incompleto a las pruebas que corren después.
     *
     * @var list<string>
     */
    private const MIGRACIONES = [
        'database/migrations/Nucleo/2026_09_30_000001_crear_tabla_usuarios.php',
        'database/migrations/Nucleo/2026_09_30_000002_crear_tabla_bitacora_auditoria.php',
        'database/migrations/Nucleo/2026_09_30_000003_crear_tablas_sesiones_y_restablecimientos.php',
        'database/migrations/Nucleo/2026_09_30_000004_crear_tablas_trabajos_cola.php',
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

    protected function setUp(): void
    {
        parent::setUp();

        // La tabla comercios es de M2: con llave foránea hace falta un comercio real.
        if (DB::getDriverName() === 'sqlite' && ! empty(DB::select('PRAGMA foreign_key_list(usuarios)'))) {
            $this->markTestSkipped('usuarios tiene llave foránea a comercios (M2): requiere un comercio real.');
        }
    }

    /**
     * Crea la invitación con crearCuenta y devuelve el usuario y el token del enlace del correo.
     *
     * @return array{0: Usuario, 1: string}
     */
    private function invitar(string $correo = 'encargado@example.com'): array
    {
        Notification::fake();

        $usuario = $this->app->make(ServicioIdentidadInterface::class)->crearCuenta([
            'nombre' => 'Elena Mora',
            'correo' => $correo,
        ], RolUsuario::EncargadoComercio, idComercio: 7);

        $enlace = null;
        Notification::assertSentTo($usuario, InvitacionEncargadoComercio::class, function ($notificacion) use ($usuario, &$enlace) {
            $enlace = $notificacion->toMail($usuario)->actionUrl;

            return true;
        });

        $this->assertStringContainsString('/crear-contrasena?', $enlace);
        parse_str((string) parse_url($enlace, PHP_URL_QUERY), $consulta);
        $this->assertSame($correo, $consulta['correo']);

        return [$usuario, $consulta['token']];
    }

    /** @param array<string, mixed> $cambios */
    private function cuerpo(string $token, array $cambios = []): array
    {
        return array_merge([
            'token' => $token,
            'correo' => 'encargado@example.com',
            'contrasena' => 'clave-nueva-123',
            'contrasena_confirmation' => 'clave-nueva-123',
        ], $cambios);
    }

    public function test_crea_la_contrasena_con_el_token_de_la_invitacion_y_luego_inicia_sesion(): void
    {
        [$usuario, $token] = $this->invitar();

        $this->postJson('/crear-contrasena', $this->cuerpo($token))
            ->assertOk()
            ->assertJsonPath('mensaje', 'Contraseña definida. Ya puede iniciar sesión.');

        $this->assertTrue(Hash::check('clave-nueva-123', $usuario->fresh()->contrasena));

        $this->postJson('/login', ['correo' => 'encargado@example.com', 'contrasena' => 'clave-nueva-123'])
            ->assertOk()
            ->assertJsonPath('user.id_usuario', $usuario->id_usuario)
            ->assertJsonPath('user.rol', RolUsuario::EncargadoComercio->value)
            ->assertJsonPath('user.id_comercio', 7);
    }

    public function test_el_token_se_consume_y_no_sirve_dos_veces(): void
    {
        [, $token] = $this->invitar();

        $this->postJson('/crear-contrasena', $this->cuerpo($token))->assertOk();

        $this->postJson('/crear-contrasena', $this->cuerpo($token, [
            'contrasena' => 'otra-clave-456',
            'contrasena_confirmation' => 'otra-clave-456',
        ]))->assertUnprocessable()->assertJsonPath('error.codigo', 'ERR-005');
    }

    public function test_token_incorrecto_da_err_005_y_no_cambia_la_contrasena(): void
    {
        [$usuario] = $this->invitar();
        $antes = $usuario->fresh()->contrasena;

        $this->postJson('/crear-contrasena', $this->cuerpo('token-falso'))
            ->assertUnprocessable()
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonValidationErrors(['token' => 'El enlace de invitación no es válido o ya venció.'], 'error.detalles');

        $this->assertSame($antes, $usuario->fresh()->contrasena);
    }

    public function test_correo_desconocido_da_el_mismo_error_que_un_token_incorrecto(): void
    {
        [, $token] = $this->invitar();

        $this->postJson('/crear-contrasena', $this->cuerpo($token, ['correo' => 'nadie@example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token' => 'El enlace de invitación no es válido o ya venció.'], 'error.detalles');
    }

    public function test_cuenta_inactiva_no_puede_definir_contrasena(): void
    {
        [$usuario, $token] = $this->invitar();
        $usuario->forceFill(['activo' => false])->save();

        $this->postJson('/crear-contrasena', $this->cuerpo($token))->assertUnprocessable();
    }

    public function test_el_correo_se_acepta_sin_distinguir_mayusculas(): void
    {
        [, $token] = $this->invitar();

        $this->postJson('/crear-contrasena', $this->cuerpo($token, ['correo' => 'Encargado@Example.com']))->assertOk();
    }

    public function test_la_invitacion_sigue_vigente_a_las_24_horas_y_vence_pasado_el_plazo(): void
    {
        [, $token] = $this->invitar();
        $plazo = (int) config('auth.passwords.invitaciones.expire');

        $this->assertGreaterThanOrEqual(1440, $plazo, 'La invitación debe durar al menos un día.');

        $this->travel($plazo + 1)->minutes();

        $this->postJson('/crear-contrasena', $this->cuerpo($token))
            ->assertUnprocessable()
            ->assertJsonPath('error.codigo', 'ERR-005');
    }

    public function test_la_invitacion_sigue_vigente_poco_antes_del_plazo(): void
    {
        [, $token] = $this->invitar();
        $plazo = (int) config('auth.passwords.invitaciones.expire');

        $this->travel($plazo - 5)->minutes();

        $this->postJson('/crear-contrasena', $this->cuerpo($token))->assertOk();
    }

    public function test_contrasena_corta_sin_confirmacion_o_campos_faltantes_dan_err_005(): void
    {
        [, $token] = $this->invitar();

        $this->postJson('/crear-contrasena', $this->cuerpo($token, [
            'contrasena' => 'corta',
            'contrasena_confirmation' => 'corta',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['contrasena'], 'error.detalles');

        $this->postJson('/crear-contrasena', $this->cuerpo($token, ['contrasena_confirmation' => 'distinta-123']))
            ->assertUnprocessable()->assertJsonValidationErrors(['contrasena'], 'error.detalles');

        $this->postJson('/crear-contrasena', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonValidationErrors(['token', 'correo', 'contrasena'], 'error.detalles');

        // Ningún intento fallido consumió el token.
        $this->postJson('/crear-contrasena', $this->cuerpo($token))->assertOk();
    }

    public function test_no_inicia_sesion_automaticamente(): void
    {
        [, $token] = $this->invitar();

        $this->postJson('/crear-contrasena', $this->cuerpo($token))->assertOk();

        $this->assertGuest('web');
    }
}
