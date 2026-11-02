<?php

declare(strict_types=1);

namespace Tests\Feature\Nucleo;

use App\Exceptions\Nucleo\ErrorNucleoException;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ManejadorErroresTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/test-nucleo/validacion', function () {
            request()->validate([
                'email' => 'required|email',
            ]);

            return response()->json(['ok' => true]);
        });

        Route::get('/api/test-nucleo/autenticacion', function () {
            throw new AuthenticationException;
        });

        Route::get('/api/test-nucleo/autorizacion', function () {
            throw new AuthorizationException;
        });

        Route::get('/api/test-nucleo/no-encontrado', function () {
            throw new NotFoundHttpException;
        });

        Route::get('/api/test-nucleo/aislamiento', function () {
            throw ErrorNucleoException::recursoNoAccesible();
        });

        Route::get('/api/test-nucleo/proveedor', function () {
            throw ErrorNucleoException::proveedorNoResponde('M2_Comercios', 'Conexión rechazada');
        });

        Route::get('/api/test-nucleo/error-interno', function () {
            throw new Exception('Error inesperado de bajo nivel');
        });
    }

    public function test_captura_validacion_y_responde_err_005(): void
    {
        $response = $this->postJson('/api/test-nucleo/validacion', []);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => [
                'codigo' => 'ERR-005',
                'mensaje' => 'Revisa los datos marcados (detalle por campo en detalles).',
            ],
        ]);
        $this->assertArrayHasKey('email', $response->json('error.detalles'));
    }

    public function test_captura_autenticacion_y_responde_err_001(): void
    {
        $response = $this->getJson('/api/test-nucleo/autenticacion');

        $response->assertStatus(401);
        $response->assertExactJson([
            'error' => [
                'codigo' => 'ERR-001',
                'mensaje' => 'Tu sesión expiró, inicia sesión de nuevo.',
                'detalles' => null,
            ],
        ]);
    }

    public function test_captura_autorizacion_y_responde_err_002(): void
    {
        $response = $this->getJson('/api/test-nucleo/autorizacion');

        $response->assertStatus(403);
        $response->assertExactJson([
            'error' => [
                'codigo' => 'ERR-002',
                'mensaje' => 'No se encuentra autorizado para realizar esta acción.',
                'detalles' => null,
            ],
        ]);
    }

    public function test_captura_no_encontrado_y_responde_err_004(): void
    {
        $response = $this->getJson('/api/test-nucleo/no-encontrado');

        $response->assertStatus(404);
        $response->assertExactJson([
            'error' => [
                'codigo' => 'ERR-004',
                'mensaje' => 'No se encontró lo que buscas.',
                'detalles' => null,
            ],
        ]);
    }

    public function test_captura_recurso_ajeno_por_aislamiento_y_responde_err_003_en_404(): void
    {
        $response = $this->getJson('/api/test-nucleo/aislamiento');

        $response->assertStatus(404);
        $response->assertExactJson([
            'error' => [
                'codigo' => 'ERR-003',
                'mensaje' => 'El recurso solicitado no existe o no es accesible.',
                'detalles' => null,
            ],
        ]);
    }

    public function test_captura_falla_de_proveedor_y_responde_err_006_en_503(): void
    {
        $response = $this->getJson('/api/test-nucleo/proveedor');

        $response->assertStatus(503);
        $response->assertJson([
            'error' => [
                'codigo' => 'ERR-006',
                'mensaje' => 'Servicio temporalmente no disponible, intentar de nuevo.',
                'detalles' => [
                    'modulo' => 'M2_Comercios',
                    'motivo' => 'Conexión rechazada',
                ],
            ],
        ]);
    }

    public function test_captura_falla_interna_y_no_revela_detalles_internos(): void
    {
        $response = $this->getJson('/api/test-nucleo/error-interno');

        $response->assertStatus(500);
        $response->assertExactJson([
            'error' => [
                'codigo' => 'ERR-006',
                'mensaje' => 'Servicio temporalmente no disponible, intentar de nuevo.',
                'detalles' => null,
            ],
        ]);
    }
}
