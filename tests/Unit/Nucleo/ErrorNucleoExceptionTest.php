<?php

declare(strict_types=1);

namespace Tests\Unit\Nucleo;

use App\Contracts\Nucleo\ErrorEstandarInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;
use Illuminate\Http\Request;
use Tests\TestCase;

class ErrorNucleoExceptionTest extends TestCase
{
    public function test_implementa_el_contrato_error_estandar(): void
    {
        $ex = ErrorNucleoException::recursoNoAccesible();

        $this->assertInstanceOf(ErrorEstandarInterface::class, $ex);
        $this->assertSame('ERR-003', $ex->getCodigo());
        $this->assertSame(404, $ex->getCodigoHttp());
        $this->assertSame('El recurso solicitado no existe o no es accesible.', $ex->getMensaje());
        $this->assertSame('Nucleo', $ex->getModulo());
        $this->assertNull($ex->getDetalles());
    }

    public function test_constructores_estaticos_reservados_del_nucleo(): void
    {
        $e1 = ErrorNucleoException::sesionInvalida();
        $this->assertSame('ERR-001', $e1->getCodigo());
        $this->assertSame(401, $e1->getCodigoHttp());

        $e2 = ErrorNucleoException::rolSinPermiso();
        $this->assertSame('ERR-002', $e2->getCodigo());
        $this->assertSame(403, $e2->getCodigoHttp());

        $e3 = ErrorNucleoException::recursoNoAccesible();
        $this->assertSame('ERR-003', $e3->getCodigo());
        $this->assertSame(404, $e3->getCodigoHttp());

        $e4 = ErrorNucleoException::recursoNoEncontrado();
        $this->assertSame('ERR-004', $e4->getCodigo());
        $this->assertSame(404, $e4->getCodigoHttp());

        $detalles = ['sku' => ['El SKU es obligatorio']];
        $e5 = ErrorNucleoException::validacionFallida($detalles);
        $this->assertSame('ERR-005', $e5->getCodigo());
        $this->assertSame(422, $e5->getCodigoHttp());
        $this->assertSame($detalles, $e5->getDetalles());

        $e6 = ErrorNucleoException::proveedorNoResponde('M4_Inventario', 'Tiempo de espera agotado');
        $this->assertSame('ERR-006', $e6->getCodigo());
        $this->assertSame(503, $e6->getCodigoHttp());
        $this->assertSame([
            'modulo' => 'M4_Inventario',
            'motivo' => 'Tiempo de espera agotado',
        ], $e6->getDetalles());
    }

    public function test_permite_crear_errores_para_modulos_especificos(): void
    {
        $ex = ErrorNucleoException::paraModulo(
            'M4_Inventario',
            'ERR-M4-001',
            409,
            'No hay existencias suficientes para la cantidad solicitada.',
            ['variacion_id' => 12]
        );

        $this->assertSame('ERR-M4-001', $ex->getCodigo());
        $this->assertSame(409, $ex->getCodigoHttp());
        $this->assertSame('M4_Inventario', $ex->getModulo());
        $this->assertSame(['variacion_id' => 12], $ex->getDetalles());
    }

    public function test_to_array_y_render_cumplen_con_el_esquema_json(): void
    {
        $ex = ErrorNucleoException::recursoNoEncontrado();

        $this->assertSame([
            'error' => [
                'codigo' => 'ERR-004',
                'mensaje' => 'No se encontró lo que buscas.',
                'detalles' => null,
            ],
        ], $ex->toArray());

        $respuesta = $ex->render(Request::create('/api/test', 'GET'));
        $this->assertSame(404, $respuesta->getStatusCode());
        $this->assertSame($ex->toArray(), $respuesta->getData(true));
    }
}
