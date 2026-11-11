<?php

declare(strict_types=1);

namespace Tests\Unit\M3_Catalogo;

use App\Services\M3_Catalogo\CatalogoService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Unit\M3_Catalogo\Concerns\PreparaDatosConsultasM3;

/** buscarProductos y obtenerProducto solo exponen productos publicados de comercios activos (contrato v1.3.0). */
class BuscarProductosSoloPublicadosTest extends TestCase
{
    use PreparaDatosConsultasM3;

    private CatalogoService $servicio;

    private int $visible;

    private int $borrador;

    private int $desactivado;

    private int $comercioSuspendido;

    private int $comercioSinEstado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConsultasM3();
        $this->servicio = new CatalogoService;

        $this->comercioActivo(1, true);
        $this->comercioActivo(2, false); // suspendido
        // el comercio 3 no tiene fila en estado_comercios_catalogo

        $categoria = $this->categoria('General');

        $this->visible = $this->producto(1, $categoria, 'Visible');
        $this->borrador = $this->producto(1, $categoria, 'Borrador', 'borrador');
        $this->desactivado = $this->producto(1, $categoria, 'Desactivado', 'desactivado');
        $this->comercioSuspendido = $this->producto(2, $categoria, 'De comercio suspendido');
        $this->comercioSinEstado = $this->producto(3, $categoria, 'De comercio sin estado');

        foreach ([$this->visible, $this->borrador, $this->desactivado] as $i => $idProducto) {
            $this->variacion($idProducto, 1, 'SKU-A'.$i);
        }
        $this->variacion($this->comercioSuspendido, 2, 'SKU-B');
        $this->variacion($this->comercioSinEstado, 3, 'SKU-C');
    }

    public function test_busqueda_devuelve_solo_publicados_de_comercios_activos(): void
    {
        $resultado = $this->servicio->buscarProductos();

        $this->assertSame(1, $resultado['total_resultados']);
        $this->assertCount(1, $resultado['productos']);
        $this->assertSame($this->visible, $resultado['productos'][0]['id_producto']);
        $this->assertSame('publicado', $resultado['productos'][0]['estado_producto']);
    }

    public function test_filtro_ids_tampoco_expone_productos_ocultos(): void
    {
        $todos = [$this->visible, $this->borrador, $this->desactivado, $this->comercioSuspendido, $this->comercioSinEstado];

        $resultado = $this->servicio->buscarProductos(['ids' => implode(',', $todos)]);

        $this->assertSame([$this->visible], array_column($resultado['productos'], 'id_producto'));
    }

    public function test_obtener_detalle_de_producto_oculto_devuelve_null(): void
    {
        $this->assertNotNull($this->servicio->obtenerDetalleProducto($this->visible));

        foreach ([$this->borrador, $this->desactivado, $this->comercioSuspendido, $this->comercioSinEstado] as $id) {
            $this->assertNull($this->servicio->obtenerDetalleProducto($id));
        }
    }

    public function test_comercio_reactivado_vuelve_a_mostrar_sus_productos(): void
    {
        $this->assertNull($this->servicio->obtenerDetalleProducto($this->comercioSuspendido));

        DB::table('estado_comercios_catalogo')->where('id_comercio', 2)->update(['activo' => true]);

        $this->assertNotNull($this->servicio->obtenerDetalleProducto($this->comercioSuspendido));
    }
}
