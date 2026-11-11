<?php

declare(strict_types=1);

namespace Tests\Feature\Nucleo;

use App\Enums\Nucleo\RolUsuario;
use App\Http\Middleware\RequiereRol;
use App\Models\Nucleo\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * RequiereRol con un Usuario real (cuyo rol se castea a RolUsuario), no con objetos simulados.
 * Corre solo con las migraciones del núcleo que necesita, sobre SQLite en memoria.
 */
class RequiereRolTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
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

    private function usuario(RolUsuario $rol, bool $activo = true): Usuario
    {
        return Usuario::create([
            'nombre' => 'Ana Mora',
            'correo' => uniqid('ana', true).'@example.com',
            'contrasena' => 'secreta123',
            'rol' => $rol,
            'id_comercio' => $rol === RolUsuario::EncargadoComercio ? 7 : null,
            'activo' => $activo,
        ]);
    }

    private function pasar(?object $usuario, string ...$roles): Response
    {
        $request = Request::create('/prueba');
        $request->setUserResolver(fn () => $usuario);

        return (new RequiereRol)->handle($request, fn () => response('ok'), ...$roles);
    }

    private function codigo(Response $respuesta): string
    {
        return json_decode((string) $respuesta->getContent(), true)['error']['codigo'];
    }

    public function test_deja_pasar_a_un_usuario_real_con_el_rol_permitido(): void
    {
        $respuesta = $this->pasar($this->usuario(RolUsuario::Cliente), 'cliente');

        $this->assertSame(200, $respuesta->getStatusCode());
    }

    public function test_acepta_varios_roles_permitidos(): void
    {
        $respuesta = $this->pasar(
            $this->usuario(RolUsuario::EncargadoPlataforma),
            'encargado_plataforma',
            'encargado_comercio'
        );

        $this->assertSame(200, $respuesta->getStatusCode());
    }

    public function test_rechaza_con_err_002_a_un_usuario_real_con_otro_rol(): void
    {
        $respuesta = $this->pasar($this->usuario(RolUsuario::Cliente), 'encargado_plataforma');

        $this->assertSame(403, $respuesta->getStatusCode());
        $this->assertSame('ERR-002', $this->codigo($respuesta));
    }

    public function test_rechaza_a_un_usuario_inactivo(): void
    {
        $respuesta = $this->pasar($this->usuario(RolUsuario::Cliente, activo: false), 'cliente');

        $this->assertSame(403, $respuesta->getStatusCode());
        $this->assertSame('ERR-002', $this->codigo($respuesta));
    }

    public function test_sin_usuario_responde_err_001(): void
    {
        $respuesta = $this->pasar(null, 'cliente');

        $this->assertSame(401, $respuesta->getStatusCode());
        $this->assertSame('ERR-001', $this->codigo($respuesta));
    }

    public function test_acepta_un_rol_guardado_como_texto(): void
    {
        $respuesta = $this->pasar((object) ['activo' => true, 'rol' => 'cliente'], 'cliente');

        $this->assertSame(200, $respuesta->getStatusCode());
    }
}
