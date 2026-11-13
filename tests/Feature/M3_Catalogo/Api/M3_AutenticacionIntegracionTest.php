<?php

namespace Tests\Feature\M3_Catalogo\Api;

use App\Enums\Nucleo\RolUsuario;
use App\Models\Nucleo\Usuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Feature\M3_Catalogo\EsquemaCatalogoPrueba;
use Tests\TestCase;

class M3_AutenticacionIntegracionTest extends TestCase
{
    use EsquemaCatalogoPrueba;
    use LazilyRefreshDatabase;

    private const BASE = '/api/m3/v1';

    private const COMERCIO = 1;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => [
            'database/migrations/Nucleo/2026_09_30_000001_crear_tabla_usuarios.php',
            'database/migrations/Nucleo/2026_09_30_000002_crear_tabla_bitacora_auditoria.php',
            'database/migrations/Nucleo/2026_09_30_000003_crear_tablas_sesiones_y_restablecimientos.php',
            'database/migrations/Nucleo/2026_09_30_000004_crear_tablas_trabajos_cola.php',
            'database/migrations/Nucleo/2026_11_02_191532_agregar_inmutabilidad_a_bitacora_auditoria.php',
        ]];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['integraciones.m3.m2.modo' => 'doble']);

        $this->crearEsquemaCatalogo();

        // Las pruebas verifican el middleware CSRF real.
        $this->app->instance('env', 'local');
    }

    public function test_encargado_autenticado_con_cookies_puede_crear_producto(): void
    {
        $usuario = UsuarioFactory::new()->create([
            'rol' => RolUsuario::EncargadoComercio,
            'id_comercio' => self::COMERCIO,
            'activo' => true,
        ]);

        $this->iniciarSesionConCookies($usuario);

        $idCategoria = $this->postJson(self::BASE.'/categorias', [
            'nombre' => 'Categoría de integración',
        ])
            ->assertCreated()
            ->json('id_categoria');

        $this->postJson(self::BASE.'/productos', [
            'id_categoria' => $idCategoria,
            'nombre' => 'Producto de integración',
            'descripcion' => 'Producto registrado mediante sesión real.',
        ])
            ->assertCreated()
            ->assertJsonPath('nombre', 'Producto de integración')
            ->assertJsonPath('id_comercio', self::COMERCIO);

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Producto de integración',
            'id_comercio' => self::COMERCIO,
        ]);
    }

    public function test_cliente_autenticado_no_puede_escribir_en_catalogo(): void
    {
        $usuario = UsuarioFactory::new()->create([
            'rol' => RolUsuario::Cliente,
            'id_comercio' => null,
            'activo' => true,
        ]);

        $this->iniciarSesionConCookies($usuario);

        $this->postJson(self::BASE.'/categorias', [
            'nombre' => 'Categoría no autorizada',
        ])->assertForbidden();
    }

    public function test_encargado_plataforma_no_puede_escribir_en_catalogo(): void
    {
        $usuario = UsuarioFactory::new()->create([
            'rol' => RolUsuario::EncargadoPlataforma,
            'id_comercio' => null,
            'activo' => true,
        ]);

        $this->iniciarSesionConCookies($usuario);

        $this->postJson(self::BASE.'/categorias', [
            'nombre' => 'Categoría no autorizada',
        ])->assertForbidden();
    }

    public function test_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        $usuario = UsuarioFactory::new()->inactivo()->create([
            'rol' => RolUsuario::EncargadoComercio,
            'id_comercio' => self::COMERCIO,
        ]);

        // 1. Solicitar la cookie CSRF.
        $csrf = $this->getJson('/sanctum/csrf-cookie')
            ->assertNoContent();

        $nombreCookieSesion = config('session.cookie');

        // 2. Preparar las cookies y el token CSRF.
        $this->withUnencryptedCookies([
            $nombreCookieSesion => $csrf
                ->getCookie($nombreCookieSesion, false)
                ->getValue(),
        ])->withHeader(
            'X-XSRF-TOKEN',
            $csrf->getCookie('XSRF-TOKEN', false)->getValue()
        );

        // 3. Intentar iniciar sesion con el usuario inactivo.
        $respuesta = $this->postJson('/login', [
            'correo' => $usuario->correo,
            'contrasena' => 'password',
        ]);

        // 4. Comprobar que se rechaza la autenticacion.
        $respuesta
            ->assertUnprocessable()
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonStructure([
                'error' => [
                    'detalles' => ['correo'],
                ],
            ]);

        $this->assertGuest('web');
    }

    public function test_un_comercio_no_puede_modificar_productos_de_otro_comercio(): void
    {
        // 1. Crear el encargado del comercio 1.
        $encargadoA = UsuarioFactory::new()->create([
            'rol' => RolUsuario::EncargadoComercio,
            'id_comercio' => 1,
            'activo' => true,
        ]);

        // 2. Autenticar al encargado A mediante cookies reales.
        $this->iniciarSesionConCookies($encargadoA);

        // 3. Crear una categoría para el comercio 1.
        $idCategoria = $this->postJson(self::BASE.'/categorias', [
            'nombre' => 'Categoría comercio A',
        ])
            ->assertCreated()
            ->json('id_categoria');

        // 4. Crear un producto perteneciente al comercio 1.
        $idProducto = $this->postJson(self::BASE.'/productos', [
            'id_categoria' => $idCategoria,
            'nombre' => 'Producto privado del comercio A',
        ])
            ->assertCreated()
            ->assertJsonPath('id_comercio', 1)
            ->json('id_producto');

        // 5. Crear el encargado del comercio 34.
        $encargadoB = UsuarioFactory::new()->create([
            'rol' => RolUsuario::EncargadoComercio,
            'id_comercio' => 34,
            'activo' => true,
        ]);

        // 6. Autenticar al encargado B mediante una nueva sesión.
        $this->iniciarSesionConCookies($encargadoB);

        // 7. Intentar modificar el producto del comercio A.
        $this->patchJson(self::BASE."/productos/{$idProducto}", [
            'destacado' => true,
        ])
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-004');
    }

    private function iniciarSesionConCookies(Usuario $usuario): void
    {
        $this->withHeader('Origin', 'http://localhost:5173')
            ->withCredentials();

        // 1. Solicitar la cookie CSRF.
        $csrf = $this->getJson('/sanctum/csrf-cookie')
            ->assertNoContent();

        $nombreCookieSesion = config('session.cookie');

        // 2. Conservar la cookie de sesión y enviar el token CSRF.
        $this->withUnencryptedCookies([
            $nombreCookieSesion => $csrf
                ->getCookie($nombreCookieSesion, false)
                ->getValue(),
        ])->withHeader(
            'X-XSRF-TOKEN',
            $csrf->getCookie('XSRF-TOKEN', false)->getValue()
        );

        // 3. Iniciar sesión mediante el endpoint real.
        $login = $this->postJson('/login', [
            'correo' => $usuario->correo,
            'contrasena' => 'password',
        ])->assertOk();

        // 4. Recuperar la nueva sesión después del login.
        $this->app['auth']->forgetGuards();

        $this->withUnencryptedCookies([
            $nombreCookieSesion => $login
                ->getCookie($nombreCookieSesion, false)
                ->getValue(),
        ])->withHeader(
            'X-XSRF-TOKEN',
            $login->getCookie('XSRF-TOKEN', false)->getValue()
        );
    }
}
