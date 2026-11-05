<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SesionTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Estas pruebas usan el esquema de identidad existente, sin ejecutar
     * las migraciones PostgreSQL de los otros módulos sobre SQLite.
     *
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        return ['--path' => 'database/migrations/Nucleo/2026_09_30_000001_crear_tabla_usuarios.php'];
    }

    public function test_login_authenticates_and_rotates_the_session(): void
    {
        $user = Usuario::factory()->create();
        $this->withSession(['_token' => 'previous-token']);
        $previousId = session()->getId();

        $this->postJson('/login', ['correo' => $user->correo, 'contrasena' => 'password'])
            ->assertOk()
            ->assertExactJson(['user' => $user->only(['id_usuario', 'nombre', 'correo', 'rol', 'id_comercio'])])
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($previousId, session()->getId());
        $this->assertNotSame('previous-token', session()->token());
    }

    #[TestWith(['incorrect-password', false])]
    #[TestWith(['password', true])]
    public function test_invalid_credentials_return_422_without_authentication(string $password, bool $unknownEmail): void
    {
        $user = Usuario::factory()->create();

        $this->postJson('/login', [
            'correo' => $unknownEmail ? 'unknown@example.com' : $user->correo,
            'contrasena' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'correo' => 'Las credenciales proporcionadas son incorrectas.',
        ], 'error.detalles')->assertJsonPath('error.codigo', 'ERR-005');

        $this->assertGuest('web');
    }

    public function test_inactive_account_cannot_login(): void
    {
        $user = Usuario::factory()->inactivo()->create();

        $this->postJson('/login', ['correo' => $user->correo, 'contrasena' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonPath('error.detalles.correo.0', 'Las credenciales proporcionadas son incorrectas.');

        $this->assertGuest('web');
    }

    #[TestWith(['cliente', null])]
    #[TestWith(['encargado_plataforma', null])]
    #[TestWith(['encargado_comercio', 42])]
    public function test_identity_comes_from_database_and_ignores_submitted_privileges(string $rol, ?int $idComercio): void
    {
        $user = Usuario::factory()->create(['rol' => $rol, 'id_comercio' => $idComercio]);
        $expected = ['user' => [
            'id_usuario' => $user->id_usuario,
            'nombre' => $user->nombre,
            'correo' => $user->correo,
            'rol' => $rol,
            'id_comercio' => $idComercio,
        ]];

        $this->postJson('/login', [
            'correo' => $user->correo,
            'contrasena' => 'password',
            'rol' => 'otro_rol',
            'id_comercio' => 999,
        ])->assertOk()->assertExactJson($expected);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user')->assertOk()->assertExactJson($expected);
        $this->assertArrayNotHasKey('contrasena', $user->toArray());
    }

    public function test_password_whitespace_is_preserved(): void
    {
        $user = Usuario::factory()->create(['contrasena' => ' password ']);

        $this->postJson('/login', ['correo' => $user->correo, 'contrasena' => ' password '])
            ->assertOk();

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_empty_login_returns_422_validation_errors(): void
    {
        $this->postJson('/login', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['correo', 'contrasena'], 'error.detalles')->assertJsonPath('error.codigo', 'ERR-005');
    }

    public function test_malformed_email_returns_422(): void
    {
        $this->postJson('/login', ['correo' => 'not-an-email', 'contrasena' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors(['correo'], 'error.detalles');
    }

    public function test_guest_cannot_read_the_current_user_or_logout(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/logout')->assertUnauthorized();
    }

    public function test_logout_clears_session_data_and_rotates_csrf(): void
    {
        $user = Usuario::factory()->create();
        $this->actingAs($user, 'web')->withSession([
            'comercio_id' => 123,
            '_token' => 'previous-token',
        ]);
        $previousId = session()->getId();

        $this->postJson('/logout')->assertNoContent()->assertSessionMissing('comercio_id');

        $this->assertGuest('web');
        $this->assertNotSame($previousId, session()->getId());
        $this->assertNotSame('previous-token', session()->token());
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    #[TestWith(['/login'])]
    #[TestWith(['/logout'])]
    public function test_mutations_without_csrf_return_419(string $path): void
    {
        // Laravel omite CSRF en testing; local habilita el middleware real.
        $this->app->instance('env', 'local');

        $this->postJson($path, [])->assertStatus(419);
    }

    public function test_spa_can_login_and_read_user_using_session_cookies(): void
    {
        $user = Usuario::factory()->create();
        $this->app->instance('env', 'local');
        $this->withHeader('Origin', 'http://localhost:5173')->withCredentials();

        $csrf = $this->getJson('/sanctum/csrf-cookie')->assertNoContent();
        $sessionCookie = config('session.cookie');
        $this->withUnencryptedCookies([
            $sessionCookie => $csrf->getCookie($sessionCookie, false)->getValue(),
        ])->withHeader('X-XSRF-TOKEN', $csrf->getCookie('XSRF-TOKEN', false)->getValue());

        $login = $this->postJson('/login', ['correo' => $user->correo, 'contrasena' => 'password'])
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withUnencryptedCookies([
            $sessionCookie => $login->getCookie($sessionCookie, false)->getValue(),
        ])->getJson('/api/user')->assertOk()
            ->assertExactJson(['user' => $user->only(['id_usuario', 'nombre', 'correo', 'rol', 'id_comercio'])]);
    }

    public function test_login_is_limited_to_five_requests_per_minute(): void
    {
        Usuario::factory()->create(['correo' => 'limited@example.com']);
        $this->freezeTime();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', ['correo' => 'limited@example.com', 'contrasena' => 'incorrect'])
                ->assertUnprocessable();
        }

        $this->postJson('/login', ['correo' => 'LIMITED@example.com', 'contrasena' => 'incorrect'])
            ->assertTooManyRequests()->assertJsonPath('error.codigo', 'ERR-429');

        $this->postJson('/login', ['correo' => 'another@example.com', 'contrasena' => 'incorrect'])
            ->assertUnprocessable();
    }

    public function test_cors_permits_credentials_for_the_configured_spa(): void
    {
        $this->options('/login', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,x-xsrf-token',
        ])->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_cors_does_not_allow_an_unknown_origin(): void
    {
        $this->options('/login', [], [
            'Origin' => 'https://unknown.example',
            'Access-Control-Request-Method' => 'POST',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_session_cookie_is_secure_and_http_only_when_https_is_configured(): void
    {
        config(['session.secure' => true]);
        $response = $this->getJson('/sanctum/csrf-cookie')->assertNoContent();
        $cookie = $response->getCookie(config('session.cookie'), false);

        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertFalse($response->getCookie('XSRF-TOKEN', false)->isHttpOnly());
    }
}
