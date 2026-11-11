<?php

declare(strict_types=1);

namespace Tests\Feature\Nucleo;

use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\Nucleo\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * ServicioIdentidad::actualizarCorreo. Corre sobre SQLite en memoria.
 */
class ActualizarCorreoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Migraciones del núcleo que esta prueba carga (todas las tablas del núcleo, para no dejar
     * un esquema incompleto a las pruebas que corren después en el mismo proceso). No incluye las
     * de otros módulos, que pueden traer SQL exclusivo de PostgreSQL y no corren en SQLite.
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

    private function servicio(): ServicioIdentidadInterface
    {
        return $this->app->make(ServicioIdentidadInterface::class);
    }

    private function cliente(string $correo = 'ana@example.com'): Usuario
    {
        return $this->servicio()->crearCuenta([
            'nombre' => 'Ana Mora',
            'correo' => $correo,
            'contrasena' => 'secreta123',
        ], RolUsuario::Cliente);
    }

    private function errorAl(callable $accion): ErrorNucleoException
    {
        try {
            $accion();
        } catch (ErrorNucleoException $e) {
            return $e;
        }

        $this->fail('Debía lanzar ErrorNucleoException.');
    }

    public function test_cambia_el_correo_en_minusculas_y_sin_espacios(): void
    {
        $usuario = $this->cliente();

        $this->servicio()->actualizarCorreo($usuario->id_usuario, '  Nuevo.Correo@Example.COM ');

        $this->assertSame('nuevo.correo@example.com', $usuario->fresh()->correo);
    }

    public function test_el_mismo_correo_del_usuario_no_cuenta_como_repetido(): void
    {
        $usuario = $this->cliente('ana@example.com');

        $this->servicio()->actualizarCorreo($usuario->id_usuario, 'ANA@example.com');

        $this->assertSame('ana@example.com', $usuario->fresh()->correo);
    }

    public function test_correo_de_otro_usuario_da_err_005_en_detalles_correo(): void
    {
        $this->cliente('ana@example.com');
        $otro = $this->cliente('luis@example.com');

        $error = $this->errorAl(fn () => $this->servicio()->actualizarCorreo($otro->id_usuario, 'Ana@Example.com'));

        $this->assertSame('ERR-005', $error->codigo);
        $this->assertArrayHasKey('correo', $error->detalles);
        $this->assertSame('luis@example.com', $otro->fresh()->correo);
    }

    public function test_formato_invalido_da_err_005(): void
    {
        $usuario = $this->cliente();

        $error = $this->errorAl(fn () => $this->servicio()->actualizarCorreo($usuario->id_usuario, 'no-es-un-correo'));

        $this->assertSame('ERR-005', $error->codigo);
        $this->assertArrayHasKey('correo', $error->detalles);
        $this->assertSame('ana@example.com', $usuario->fresh()->correo);
    }

    public function test_correo_de_mas_de_150_caracteres_da_err_005(): void
    {
        $usuario = $this->cliente();
        $largo = str_repeat('a', 145).'@example.com';

        $error = $this->errorAl(fn () => $this->servicio()->actualizarCorreo($usuario->id_usuario, $largo));

        $this->assertSame('ERR-005', $error->codigo);
        $this->assertArrayHasKey('correo', $error->detalles);
    }

    public function test_correo_vacio_da_err_005(): void
    {
        $usuario = $this->cliente();

        $error = $this->errorAl(fn () => $this->servicio()->actualizarCorreo($usuario->id_usuario, '   '));

        $this->assertSame('ERR-005', $error->codigo);
        $this->assertArrayHasKey('correo', $error->detalles);
    }

    public function test_usuario_inexistente_da_err_004(): void
    {
        $error = $this->errorAl(fn () => $this->servicio()->actualizarCorreo(999999, 'alguien@example.com'));

        $this->assertSame('ERR-004', $error->codigo);
    }

    public function test_si_el_modulo_revierte_su_transaccion_el_cambio_tambien_se_revierte(): void
    {
        $usuario = $this->cliente('ana@example.com');

        try {
            DB::transaction(function () use ($usuario): void {
                $this->servicio()->actualizarCorreo($usuario->id_usuario, 'nuevo@example.com');

                throw new RuntimeException('Falla posterior del módulo que llama.');
            });
        } catch (RuntimeException) {
            // Esperado: la transacción del módulo se revierte.
        }

        $this->assertSame('ana@example.com', $usuario->fresh()->correo);
    }
}
