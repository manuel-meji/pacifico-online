<?php

declare(strict_types=1);

namespace Tests\Unit\M3_Catalogo;

use App\Services\M3_Catalogo\CatalogoService;
use Tests\TestCase;
use Tests\Unit\M3_Catalogo\Concerns\PreparaDatosConsultasM3;

/** Contrato v1.3.0: id_categoria incluye tambien los productos de todas sus subcategorias. */
class BuscarPorCategoriaIncluyeSubcategoriasTest extends TestCase
{
    use PreparaDatosConsultasM3;

    private CatalogoService $servicio;

    /** @var array<string, int> */
    private array $cat = [];

    /** @var array<string, int> */
    private array $prod = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConsultasM3();
        $this->servicio = new CatalogoService;
        $this->comercioActivo(1, true);

        // Deportes > Calzado > Tenis ; Moda (aparte)
        $this->cat['deportes'] = $this->categoria('Deportes');
        $this->cat['calzado'] = $this->categoria('Calzado', $this->cat['deportes']);
        $this->cat['tenis'] = $this->categoria('Tenis', $this->cat['calzado']);
        $this->cat['moda'] = $this->categoria('Moda');

        foreach (['deportes', 'calzado', 'tenis', 'moda'] as $i => $clave) {
            $this->prod[$clave] = $this->producto(1, $this->cat[$clave], 'Producto '.$clave);
            $this->variacion($this->prod[$clave], 1, 'SKU-'.$i);
        }
    }

    /** @param array<string, mixed> $filtros */
    private function ids(array $filtros): array
    {
        $ids = array_column($this->servicio->buscarProductos($filtros)['productos'], 'id_producto');
        sort($ids);

        return $ids;
    }

    public function test_categoria_raiz_incluye_hijos_y_nietos(): void
    {
        $esperado = [$this->prod['deportes'], $this->prod['calzado'], $this->prod['tenis']];
        sort($esperado);

        $this->assertSame($esperado, $this->ids(['id_categoria' => $this->cat['deportes']]));
    }

    public function test_subcategoria_incluye_solo_su_rama(): void
    {
        $esperado = [$this->prod['calzado'], $this->prod['tenis']];
        sort($esperado);

        $this->assertSame($esperado, $this->ids(['id_categoria' => $this->cat['calzado']]));
    }

    public function test_categoria_hoja_y_categoria_aparte_no_se_mezclan(): void
    {
        $this->assertSame([$this->prod['tenis']], $this->ids(['id_categoria' => $this->cat['tenis']]));
        $this->assertSame([$this->prod['moda']], $this->ids(['id_categoria' => $this->cat['moda']]));
    }

    public function test_listar_categorias_devuelve_lista_plana_con_padre(): void
    {
        $lista = $this->servicio->listarCategorias();

        $this->assertCount(4, $lista);
        $porId = array_column($lista, null, 'id_categoria');
        $this->assertNull($porId[$this->cat['deportes']]['id_categoria_padre']);
        $this->assertSame($this->cat['deportes'], $porId[$this->cat['calzado']]['id_categoria_padre']);
        $this->assertArrayNotHasKey('subcategorias', $porId[$this->cat['deportes']]);
    }
}
