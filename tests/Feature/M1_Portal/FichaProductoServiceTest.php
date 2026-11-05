<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M1_Portal\DTO\Disponibilidad;
use App\Services\M1_Portal\DTO\FichaProductoDTO;
use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use App\Services\M1_Portal\DTO\VariacionDTO;
use App\Services\M1_Portal\FichaProductoService;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use Tests\Doubles\M1\CatalogoConComercioSuspendido;
use Tests\Doubles\M1\CatalogoNoDisponible;
use Tests\Doubles\M1\CatalogoRelacionadosNoDisponible;
use Tests\Doubles\M1\ComerciosNoDisponible;
use Tests\Doubles\M1\InventarioNoDisponible;
use Tests\TestCase;

/**
 * CU-003 Consultar ficha de producto (FichaProductoService) contra los dobles
 * de M2, M3 y M4.
 */
class FichaProductoServiceTest extends TestCase
{
    // ---------------------------------------------------------------
    // Flujo básico y disponibilidad (RN-M1-04)
    // ---------------------------------------------------------------

    public function test_la_ficha_trae_el_comercio_la_marca_y_las_variaciones(): void
    {
        $ficha = $this->ficha(DatosPrueba::PRODUCTO_CAMISA_LINO);

        $this->assertSame(DatosPrueba::PRODUCTO_CAMISA_LINO, $ficha->idProducto);
        $this->assertSame('Tienda La Ceiba', $ficha->comercio->nombreComercial);
        $this->assertSame('Ceiba Wear', $ficha->marca);
        $this->assertSame([
            DatosPrueba::VARIACION_CAMISA_S,
            DatosPrueba::VARIACION_CAMISA_M_AGOTADA,
            DatosPrueba::VARIACION_CAMISA_L_BAJO_STOCK,
        ], array_keys($this->variaciones($ficha)));
        $this->assertTrue($ficha->compraHabilitada);
    }

    public function test_solo_son_comprables_las_variaciones_con_unidades(): void
    {
        $variaciones = $this->variaciones($this->ficha(DatosPrueba::PRODUCTO_CAMISA_LINO));

        $this->assertTrue($variaciones[DatosPrueba::VARIACION_CAMISA_S]->comprable);
        $this->assertSame(Disponibilidad::Disponible, $variaciones[DatosPrueba::VARIACION_CAMISA_S]->disponibilidad);
        $this->assertFalse($variaciones[DatosPrueba::VARIACION_CAMISA_M_AGOTADA]->comprable);
        $this->assertSame(Disponibilidad::Agotado, $variaciones[DatosPrueba::VARIACION_CAMISA_M_AGOTADA]->disponibilidad);
    }

    public function test_un_producto_agotado_se_muestra_sin_compra_habilitada_a2(): void
    {
        $ficha = $this->ficha(DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO);

        $this->assertFalse($ficha->compraHabilitada);

        foreach ($ficha->variaciones as $variacion) {
            $this->assertSame(Disponibilidad::Agotado, $variacion->disponibilidad);
        }
    }

    public function test_una_variacion_sin_venta_habilitada_no_es_comprable_aunque_tenga_unidades(): void
    {
        $variaciones = $this->variaciones($this->ficha(DatosPrueba::PRODUCTO_JARRON));

        $noVendible = $variaciones[DatosPrueba::VARIACION_JARRON_NO_VENDIBLE];
        $this->assertSame(Disponibilidad::Disponible, $noVendible->disponibilidad);
        $this->assertFalse($noVendible->ventaHabilitada);
        $this->assertFalse($noVendible->comprable);
        $this->assertTrue($variaciones[DatosPrueba::VARIACION_JARRON_PEQUENO]->comprable);
    }

    public function test_un_producto_sin_registro_de_inventario_no_se_puede_comprar(): void
    {
        $this->assertFalse($this->ficha(DatosPrueba::PRODUCTO_TAZA_SIN_EXISTENCIA)->compraHabilitada);
    }

    public function test_el_precio_de_cada_variacion_es_el_de_m3_rn_m1_03(): void
    {
        $deM3 = app(CatalogoClient::class)->obtenerProducto(DatosPrueba::PRODUCTO_CAMISA_LINO);
        $ficha = $this->ficha(DatosPrueba::PRODUCTO_CAMISA_LINO);

        $this->assertSame(
            array_map(fn (VariacionDTO $variacion): float => $variacion->precio, $deM3->variaciones),
            array_map(fn (VariacionDTO $variacion): float => $variacion->precio, $ficha->variaciones),
        );
    }

    // ---------------------------------------------------------------
    // Producto o comercio no visibles
    // ---------------------------------------------------------------

    public function test_un_producto_inexistente_lanza_recurso_no_encontrado_a1(): void
    {
        $this->expectException(RecursoNoEncontradoException::class);

        $this->ficha(DatosPrueba::PRODUCTO_INEXISTENTE);
    }

    public function test_un_producto_de_comercio_suspendido_lanza_recurso_no_encontrado_rn_m1_01_y_rn_m1_02(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoConComercioSuspendido::class]);

        $this->expectException(RecursoNoEncontradoException::class);

        $this->ficha(DatosPrueba::PRODUCTO_DE_COMERCIO_SUSPENDIDO);
    }

    // ---------------------------------------------------------------
    // Fallas de los proveedores
    // ---------------------------------------------------------------

    public function test_si_m3_no_responde_lanza_err_006_a3(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoNoDisponible::class]);

        $this->expectException(DependenciaNoDisponibleException::class);

        $this->ficha(DatosPrueba::PRODUCTO_CAMISA_LINO);
    }

    public function test_si_m2_no_responde_se_muestra_con_el_comercio_provisional(): void
    {
        config(['integraciones.m1.m2.doble' => ComerciosNoDisponible::class]);

        $ficha = $this->ficha(DatosPrueba::PRODUCTO_CAMISA_LINO);

        $this->assertSame(DatosPrueba::COMERCIO_LA_CEIBA, $ficha->comercio->idComercio);
        $this->assertSame('', $ficha->comercio->nombreComercial);
        $this->assertTrue($ficha->compraHabilitada);
    }

    public function test_si_m4_no_responde_se_muestra_sin_compra_habilitada(): void
    {
        config(['integraciones.m1.m4.doble' => InventarioNoDisponible::class]);

        $ficha = $this->ficha(DatosPrueba::PRODUCTO_CAMISA_LINO);

        $this->assertSame('Tienda La Ceiba', $ficha->comercio->nombreComercial);
        $this->assertFalse($ficha->compraHabilitada);
    }

    public function test_si_m3_falla_solo_en_los_relacionados_se_muestra_sin_ellos(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoRelacionadosNoDisponible::class]);

        $ficha = $this->ficha(DatosPrueba::PRODUCTO_CAMISA_LINO);

        $this->assertSame(DatosPrueba::PRODUCTO_CAMISA_LINO, $ficha->idProducto);
        $this->assertSame([], $ficha->relacionados);
    }

    // ---------------------------------------------------------------
    // Relacionados
    // ---------------------------------------------------------------

    public function test_los_relacionados_traen_el_comercio_y_la_disponibilidad(): void
    {
        $relacionados = $this->ficha(DatosPrueba::PRODUCTO_CAMISA_LINO)->relacionados;

        $this->assertSame(
            [DatosPrueba::PRODUCTO_SOMBRERO_AGOTADO, DatosPrueba::PRODUCTO_JARRON],
            array_map(fn (ProductoResumenDTO $producto): int => $producto->idProducto, $relacionados),
        );
        $this->assertSame('Tienda La Ceiba', $relacionados[0]->nombreComercio);
        $this->assertSame(Disponibilidad::Agotado, $relacionados[0]->disponibilidad);
        $this->assertSame('Artesanías Guaitil', $relacionados[1]->nombreComercio);
        $this->assertSame(Disponibilidad::Disponible, $relacionados[1]->disponibilidad);
    }

    private function ficha(int $idProducto): FichaProductoDTO
    {
        return app(FichaProductoService::class)->obtenerFicha($idProducto);
    }

    /**
     * @return array<int, VariacionDTO>
     */
    private function variaciones(FichaProductoDTO $ficha): array
    {
        $porId = [];

        foreach ($ficha->variaciones as $variacion) {
            $porId[$variacion->idVariacion] = $variacion;
        }

        return $porId;
    }
}
