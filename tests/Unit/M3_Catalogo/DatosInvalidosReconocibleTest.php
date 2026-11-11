<?php

declare(strict_types=1);

namespace Tests\Unit\M3_Catalogo;

use App\Services\M3_Catalogo\CatalogoService;
use App\Services\M3_Catalogo\Excepciones\DatosInvalidosCatalogoException;
use Tests\TestCase;
use Tests\Unit\M3_Catalogo\Concerns\PreparaDatosConsultasM3;

/**
 * ERR-005 de M3 debe poder reconocerse desde fuera: los consumidores leen el codigo con
 * codigo() y los motivos con la propiedad detalles (campo => motivo).
 */
class DatosInvalidosReconocibleTest extends TestCase
{
    use PreparaDatosConsultasM3;

    public function test_codigo_devuelve_err_005(): void
    {
        $error = new DatosInvalidosCatalogoException(['orden' => 'No es valido.']);

        $this->assertSame('ERR-005', $error->codigo());
    }

    public function test_codigo_error_sigue_devolviendo_err_005(): void
    {
        $error = new DatosInvalidosCatalogoException(['orden' => 'No es valido.']);

        $this->assertSame('ERR-005', $error->codigoError());
        $this->assertSame($error->codigo(), $error->codigoError());
    }

    public function test_detalles_conserva_el_arreglo_campo_motivo(): void
    {
        $detalles = ['orden' => 'No es valido.', 'pagina' => 'Debe ser un entero mayor o igual a 1.'];

        $error = new DatosInvalidosCatalogoException($detalles);

        $this->assertSame($detalles, $error->detalles);
    }

    public function test_buscar_productos_con_orden_invalido_lanza_un_error_reconocible(): void
    {
        $this->crearEsquemaConsultasM3();

        try {
            (new CatalogoService)->buscarProductos(['orden' => 'alfabetico']);
            $this->fail('Debia lanzar DatosInvalidosCatalogoException.');
        } catch (DatosInvalidosCatalogoException $error) {
            $this->assertSame('ERR-005', $error->codigo());
            $this->assertIsArray($error->detalles);
            $this->assertArrayHasKey('orden', $error->detalles);
            $this->assertIsString($error->detalles['orden']);
        }
    }
}
