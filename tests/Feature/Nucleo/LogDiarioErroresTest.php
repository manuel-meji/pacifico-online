<?php

declare(strict_types=1);

namespace Tests\Feature\Nucleo;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\Nucleo\ManejadorErrores;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class LogDiarioErroresTest extends TestCase
{
    public function test_errores_4xx_esperados_no_se_registran_en_log(): void
    {
        $this->assertFalse(
            ManejadorErrores::debeRegistrarseEnLog(new AuthenticationException)
        );

        $this->assertFalse(
            ManejadorErrores::debeRegistrarseEnLog(new AuthorizationException)
        );

        $this->assertFalse(
            ManejadorErrores::debeRegistrarseEnLog(new NotFoundHttpException)
        );

        $this->assertFalse(
            ManejadorErrores::debeRegistrarseEnLog(new ModelNotFoundException)
        );

        $this->assertFalse(
            ManejadorErrores::debeRegistrarseEnLog(ValidationException::withMessages(['email' => 'invalido']))
        );

        $this->assertFalse(
            ManejadorErrores::debeRegistrarseEnLog(ErrorNucleoException::recursoNoAccesible())
        );
    }

    public function test_errores_5xx_y_fallas_de_proveedor_si_se_registran_en_log(): void
    {
        $this->assertTrue(
            ManejadorErrores::debeRegistrarseEnLog(new Exception('Falla crítica'))
        );

        $this->assertTrue(
            ManejadorErrores::debeRegistrarseEnLog(ErrorNucleoException::proveedorNoResponde('M4_Inventario'))
        );

        $this->assertTrue(
            ManejadorErrores::debeRegistrarseEnLog(
                ErrorNucleoException::paraModulo('M7_Contabilidad', 'ERR-M7-500', 500, 'Error asiento contable')
            )
        );
    }

    public function test_registrar_en_log_diario_escribe_estructura_con_codigo_modulo_peticion_y_traza(): void
    {
        $canalSpy = new class
        {
            public ?string $mensaje = null;

            public ?array $contexto = null;

            public function error(string $mensaje, array $contexto = []): void
            {
                $this->mensaje = $mensaje;
                $this->contexto = $contexto;
            }
        };

        Log::shouldReceive('channel')
            ->with('daily')
            ->once()
            ->andReturn($canalSpy);

        $peticion = Request::create('/api/m4/inventario/reservar', 'POST', [
            'variacion_id' => 10,
            'cantidad' => 2,
            'password' => 'supersecreto',
            'token' => 'token-privado',
        ]);

        $excepcion = ErrorNucleoException::proveedorNoResponde('M3_Catalogo', 'Timeout al consultar producto');

        ManejadorErrores::registrarEnLogDiario($excepcion, $peticion);

        $this->assertNotNull($canalSpy->contexto);
        $this->assertSame('ERR-006', $canalSpy->contexto['codigo']);
        $this->assertSame('M4_Inventario', $canalSpy->contexto['modulo_origen']);

        // Verificación de sanitización de campos sensibles en la petición
        $parametros = $canalSpy->contexto['peticion']['parametros'];
        $this->assertSame(10, $parametros['variacion_id']);
        $this->assertArrayNotHasKey('password', $parametros);
        $this->assertArrayNotHasKey('token', $parametros);

        // Verificación de la traza
        $this->assertNotEmpty($canalSpy->contexto['traza']);
    }

    public function test_detecta_modulo_de_origen_desde_namespace_o_url(): void
    {
        $reqM1 = Request::create('/m1/buscar', 'GET');
        $this->assertSame('M1_Portal', ManejadorErrores::detectarModulo(new Exception, $reqM1));

        $reqM5 = Request::create('/api/m5/pedidos', 'POST');
        $this->assertSame('M5_Pedidos', ManejadorErrores::detectarModulo(new Exception, $reqM5));

        $reqM6 = Request::create('/m6/pagos/checkout', 'POST');
        $this->assertSame('M6_Pagos', ManejadorErrores::detectarModulo(new Exception, $reqM6));

        $excepcionModulo = ErrorNucleoException::paraModulo('M2_Comercios', 'ERR-M2-001', 400, 'Error comercio');
        $this->assertSame('M2_Comercios', ManejadorErrores::detectarModulo($excepcionModulo, null));
    }

    public function test_escribe_fisicamente_en_archivo_log_diario(): void
    {
        $fecha = now()->format('Y-m-d');
        $rutaLog = storage_path("logs/laravel-{$fecha}.log");

        $ex = new \RuntimeException('Error de prueba para verificar archivo diario');
        ManejadorErrores::registrarEnLogDiario($ex);

        $this->assertFileExists($rutaLog);
        $contenido = file_get_contents($rutaLog);
        $this->assertStringContainsString('Error de prueba para verificar archivo diario', $contenido);
        $this->assertStringContainsString('ERR-006', $contenido);
    }
}
