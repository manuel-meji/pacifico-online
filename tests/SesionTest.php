<?php

namespace Tests\Feature;

use App\Models\User;
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
        return ['--path' => 'database/migrations/0001_01_01_000000_create_users_table.php'];
    }

    public function test_login_authenticates_and_rotates_the_session(): void
    {
        $user = User::factory()->create();
        $this->withSession(['_token' => 'previous-token']);
        $previousId = session()->getId();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertExactJson(['user' => $user->only(['id', 'name', 'email'])])
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($previousId, session()->getId());
        $this->assertNotSame('previous-token', session()->token());
    }

    #[TestWith(['incorrect-password', false])]
    #[TestWith(['password', true])]
    public function test_invalid_credentials_return_422_without_authentication(string $password, bool $unknownEmail): void
    {
        $user = User::factory()->create();

        $this->postJson('/login', [
            'email' => $unknownEmail ? 'unknown@example.com' : $user->email,
            'password' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'email' => 'Las credenciales proporcionadas son incorrectas.',
        ]);

        $this->assertGuest('web');
    }

    public function test_empty_login_returns_422_validation_errors(): void
    {
        $this->postJson('/login', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_malformed_email_returns_422(): void
    {
        $this->postJson('/login', ['email' => 'not-an-email', 'password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_guest_cannot_read_the_current_user_or_logout(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/logout')->assertUnauthorized();
    }

    public function test_logout_clears_session_data_and_rotates_csrf(): void
    {
        $user = User::factory()->create();
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
        $user = User::factory()->create();
        $this->app->instance('env', 'local');
        $this->withHeader('Origin', 'http://localhost:5173')->withCredentials();

        $csrf = $this->getJson('/sanctum/csrf-cookie')->assertNoContent();
        $sessionCookie = config('session.cookie');
        $this->withUnencryptedCookies([
            $sessionCookie => $csrf->getCookie($sessionCookie, false)->getValue(),
        ])->withHeader('X-XSRF-TOKEN', $csrf->getCookie('XSRF-TOKEN', false)->getValue());

        $login = $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withUnencryptedCookies([
            $sessionCookie => $login->getCookie($sessionCookie, false)->getValue(),
        ])->getJson('/api/user')->assertOk()
            ->assertExactJson(['user' => $user->only(['id', 'name', 'email'])]);
    }

    public function test_login_is_limited_to_five_requests_per_minute(): void
    {
        User::factory()->create(['email' => 'limited@example.com']);
        $this->freezeTime();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', ['email' => 'limited@example.com', 'password' => 'incorrect'])
                ->assertUnprocessable();
        }

        $this->postJson('/login', ['email' => 'limited@example.com', 'password' => 'incorrect'])
            ->assertTooManyRequests();
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
