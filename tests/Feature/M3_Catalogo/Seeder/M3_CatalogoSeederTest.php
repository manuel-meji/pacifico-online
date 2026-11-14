<?php

declare(strict_types=1);

namespace Tests\Feature\M3_Catalogo\Seeder;

use App\Events\M4_Inventario\ExistenciaAgotada;
use Database\Seeders\M3_Catalogo\M3CatalogoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Tests\Unit\M3_Catalogo\Concerns\PreparaDatosConsultasM3;

/**
 * M3CatalogoSeeder: datos de los escenarios del PC4 y reejecución segura sobre una base con datos.
 * El esquema de prueba conserva la llave foránea (id_producto, id_comercio) de las variaciones.
 */
class M3_CatalogoSeederTest extends TestCase
{
    use PreparaDatosConsultasM3;

    private const TABLAS = [
        'estado_comercios_catalogo' => 5,
        'categorias' => 6,
        'marcas' => 1,
        'productos' => 12,
        'producto_variaciones' => 14,
        'atributos_variacion' => 16,
        'imagenes_producto' => 5,
    ];

    /** Variación => [producto, comercio, SKU, precio, tarifa]. Datos de escenario, escritos aparte del seeder. */
    private const ESCENARIO = [
        1 => [1, 1, 'SDM-LOG-MQ291-NEG', 10000.0, 13.0],
        2 => [2, 1, 'SDM-AUD-BT-NEG', 15000.0, 13.0],
        3 => [3, 2, 'TML-LAMP-LED-BLA', 4500.0, 13.0],
        4 => [4, 2, 'TML-ORG-ESC-GRI', 3500.0, 13.0],
        5 => [5, 3, 'ELG-CAM-LIN-M-AZU', 25000.0, 13.0],
        6 => [6, 3, 'ELG-PAN-CHI-32-BEI', 30000.0, 13.0],
        7 => [7, 4, 'DP-CAM-DEP-S', 8990.0, 13.0],
        8 => [7, 4, 'DP-CAM-DEP-XL', 9490.0, 13.0],
        9 => [8, 4, 'DP-BOT-TER-NEG', 6750.0, 13.0],
        10 => [9, 5, 'CV-CAF-340-MED', 5425.0, 13.0],
        11 => [10, 5, 'CV-TAZ-CER-BLA', 3333.0, 13.0],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConsultasM3();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sembrar(): void
    {
        $this->seed(M3CatalogoSeeder::class);
    }

    /** @return array<string, int> */
    private function conteos(): array
    {
        return collect(self::TABLAS)->map(fn (int $esperado, string $tabla): int => DB::table($tabla)->count())->all();
    }

    private function variacionSembrada(int $id): object
    {
        return DB::table('producto_variaciones')->where('id_variacion', $id)->first();
    }

    /** Catálogo que dejaba la versión anterior del seeder (ids 1 a 11 con otros productos). */
    private function sembrarCatalogoAnterior(): void
    {
        $antes = '2026-11-10 08:00:00';

        $productos = [1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 3, 6 => 4, 7 => 4, 8 => 5, 9 => 5, 10 => 5];
        foreach ($productos as $idProducto => $idComercio) {
            DB::table('productos')->insert([
                'id_producto' => $idProducto, 'id_comercio' => $idComercio, 'id_categoria' => 1,
                'nombre' => "Producto anterior {$idProducto}", 'estado_producto' => 'publicado',
                'created_at' => $antes, 'updated_at' => $antes,
            ]);
        }

        $variaciones = [
            1 => [1, 1, 'SDM-LOG-MQ291-NEG', 10000], 2 => [2, 1, 'SDM-LOG-K835-GRIS', 28000],
            3 => [3, 2, 'TE-AUD-BT-BLA', 15000], 4 => [4, 3, 'ELG-CAM-FHD-NEG', 32000],
            5 => [5, 3, 'ELG-CAM-LIN-M-AZU', 25000], 6 => [6, 4, 'DP-TENIS-RUN-40', 38900],
            7 => [6, 4, 'DP-TENIS-RUN-42', 38900], 8 => [7, 4, 'DP-CAM-DRY-S', 18500],
            9 => [8, 5, 'CV-GRA-500G-MED', 6500], 10 => [9, 5, 'CV-MOL-340G-MED', 4200],
            11 => [10, 5, 'CV-TAZ-CER-BLA', 3333],
        ];
        foreach ($variaciones as $id => [$idProducto, $idComercio, $sku, $precio]) {
            DB::table('producto_variaciones')->insert([
                'id_variacion' => $id, 'id_producto' => $idProducto, 'id_comercio' => $idComercio, 'sku' => $sku,
                'precio' => $precio, 'tarifa_impuesto' => 13, 'disponible' => true,
                'created_at' => $antes, 'updated_at' => $antes,
            ]);
        }

        DB::table('atributos_variacion')->insert([
            ['id_variacion' => 9, 'tipo_atributo' => 'Tueste', 'valor' => 'Medio', 'created_at' => $antes, 'updated_at' => $antes],
            ['id_variacion' => 9, 'tipo_atributo' => 'Color', 'valor' => 'Café', 'created_at' => $antes, 'updated_at' => $antes],
            ['id_variacion' => 6, 'tipo_atributo' => 'Talla', 'valor' => '40', 'created_at' => $antes, 'updated_at' => $antes],
        ]);

        $imagenes = [
            1 => 'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?fm=jpg&w=600', // mouse
            2 => 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?fm=jpg&w=600', // teclado
            3 => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?fm=jpg&w=600', // audífonos
            4 => 'https://example.com/webcam-1080p.jpg',
            6 => 'https://cdn.comercio.test/foto-propia.jpg', // la puso el comercio
            7 => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?fm=jpg&w=600', // camiseta
            8 => 'https://images.unsplash.com/photo-1587734195503-904fca47e0e9?fm=jpg&w=600', // café en grano
        ];
        foreach ($imagenes as $idProducto => $url) {
            DB::table('imagenes_producto')->insert([
                'id_producto' => $idProducto, 'imagen_url' => $url, 'formato' => 'jpg', 'orden' => 1,
                'created_at' => $antes, 'updated_at' => $antes,
            ]);
        }
    }

    private function imagenes(int $idProducto): array
    {
        return DB::table('imagenes_producto')->where('id_producto', $idProducto)->orderBy('orden')->pluck('imagen_url')->all();
    }

    // ------------------------------------------------------------ datos sembrados

    public function test_siembra_las_cantidades_esperadas_en_una_base_vacia(): void
    {
        $this->sembrar();

        $this->assertSame(self::TABLAS, $this->conteos());
    }

    public function test_las_variaciones_1_a_11_cumplen_los_datos_de_escenario(): void
    {
        $this->sembrar();

        foreach (self::ESCENARIO as $id => [$idProducto, $idComercio, $sku, $precio, $tarifa]) {
            $v = $this->variacionSembrada($id);

            $this->assertSame($idProducto, (int) $v->id_producto, "producto de la variación {$id}");
            $this->assertSame($idComercio, (int) $v->id_comercio, "comercio de la variación {$id}");
            $this->assertSame($sku, $v->sku);
            $this->assertSame($precio, (float) $v->precio, "precio de la variación {$id}");
            $this->assertSame($tarifa, (float) $v->tarifa_impuesto, "tarifa de la variación {$id}");
            $this->assertTrue((bool) $v->disponible);
            $this->assertSame(
                $idComercio,
                (int) DB::table('productos')->where('id_producto', $idProducto)->value('id_comercio'),
                "el producto {$idProducto} debe ser del mismo comercio que su variación"
            );
        }

        $this->assertSame(10, DB::table('productos')->whereBetween('id_producto', [1, 10])->where('estado_producto', 'publicado')->count());
    }

    public function test_la_variacion_9_es_la_botella_termica(): void
    {
        $this->sembrar();

        $v = $this->variacionSembrada(9);

        $this->assertSame('DP-BOT-TER-NEG', $v->sku);
        $this->assertSame('Botella térmica negra 750 ml', DB::table('productos')->where('id_producto', $v->id_producto)->value('nombre'));
        $this->assertSame(
            ['Capacidad' => '750 ml', 'Color' => 'Negro'],
            DB::table('atributos_variacion')->where('id_variacion', 9)->orderBy('tipo_atributo')->pluck('valor', 'tipo_atributo')->all()
        );
    }

    public function test_conserva_los_productos_y_variaciones_de_la_demostracion_de_m4(): void
    {
        $this->sembrar();

        $this->assertSame(1, (int) DB::table('productos')->where('id_producto', 301)->value('id_comercio'));
        $this->assertSame(2, (int) DB::table('productos')->where('id_producto', 401)->value('id_comercio'));

        foreach ([501 => [301, 1, 'SDM-MON-FHD-24'], 502 => [301, 1, 'SDM-MON-FHD-24-PRO'], 601 => [401, 2, 'TE-LEN-IDP-01']] as $id => [$idProducto, $idComercio, $sku]) {
            $v = $this->variacionSembrada($id);

            $this->assertSame([$idProducto, $idComercio, $sku], [(int) $v->id_producto, (int) $v->id_comercio, $v->sku]);
        }
    }

    public function test_la_marca_no_lleva_un_logotipo_de_ejemplo(): void
    {
        $this->sembrar();

        $this->assertNull(DB::table('marcas')->where('id_marca', 1)->value('logotipo_url'));
        $this->assertSame(0, DB::table('imagenes_producto')->where('imagen_url', 'like', '%example.com%')->count());
    }

    // ------------------------------------------------------------- reejecución

    public function test_ejecutarlo_dos_veces_no_duplica_ni_cambia_los_conteos(): void
    {
        $this->sembrar();
        $primera = $this->conteos();

        $this->sembrar();

        $this->assertSame($primera, $this->conteos());
        $this->assertSame(0, DB::table('producto_variaciones')->where('sku', 'like', 'M3-SEMBRADO-TMP-%')->count());
    }

    public function test_una_variacion_agotada_por_m4_sigue_agotada_al_reejecutar(): void
    {
        $this->sembrar();

        event(new ExistenciaAgotada(4, 8, 9));
        $this->assertFalse((bool) $this->variacionSembrada(9)->disponible);

        $this->sembrar();

        $this->assertFalse((bool) $this->variacionSembrada(9)->disponible);
    }

    public function test_created_at_no_cambia_y_updated_at_si(): void
    {
        Carbon::setTestNow('2026-11-10 08:00:00');
        $this->sembrar();
        $creado = [
            'productos' => DB::table('productos')->where('id_producto', 8)->value('created_at'),
            'producto_variaciones' => $this->variacionSembrada(9)->created_at,
            'categorias' => DB::table('categorias')->where('id_categoria', 6)->value('created_at'),
            'atributos_variacion' => DB::table('atributos_variacion')->where('id_variacion', 9)->min('created_at'),
            'imagenes_producto' => DB::table('imagenes_producto')->where('id_producto', 1)->value('created_at'),
        ];

        Carbon::setTestNow('2026-11-13 15:00:00');
        $this->sembrar();

        $this->assertSame($creado['productos'], DB::table('productos')->where('id_producto', 8)->value('created_at'));
        $this->assertSame($creado['producto_variaciones'], $this->variacionSembrada(9)->created_at);
        $this->assertSame($creado['categorias'], DB::table('categorias')->where('id_categoria', 6)->value('created_at'));
        $this->assertSame($creado['atributos_variacion'], DB::table('atributos_variacion')->where('id_variacion', 9)->min('created_at'));
        $this->assertSame($creado['imagenes_producto'], DB::table('imagenes_producto')->where('id_producto', 1)->value('created_at'));

        $this->assertNotNull($creado['productos']);
        $this->assertSame('2026-11-13 15:00:00', $this->variacionSembrada(9)->updated_at);
    }

    public function test_no_cambia_el_estado_de_un_producto_ni_de_un_comercio_ya_existentes(): void
    {
        DB::table('estado_comercios_catalogo')->insert(['id_comercio' => 4, 'activo' => false]);
        $this->sembrar();
        DB::table('productos')->where('id_producto', 2)->update(['estado_producto' => 'desactivado']);

        $this->sembrar();

        $this->assertSame('desactivado', DB::table('productos')->where('id_producto', 2)->value('estado_producto'));
        $this->assertFalse((bool) DB::table('estado_comercios_catalogo')->where('id_comercio', 4)->value('activo'));
    }

    public function test_no_pisa_una_imagen_puesta_por_un_comercio(): void
    {
        $this->sembrar();

        // El comercio 1 cambia su imagen y el comercio 2 sube una a un producto que el seeder deja sin imagen.
        DB::table('imagenes_producto')->where('id_producto', 1)->update(['imagen_url' => 'https://cdn.comercio.test/mouse.jpg']);
        DB::table('imagenes_producto')->insert(['id_producto' => 3, 'imagen_url' => 'https://cdn.comercio.test/lampara.jpg', 'orden' => 1]);

        $this->sembrar();

        $this->assertSame(['https://cdn.comercio.test/mouse.jpg'], $this->imagenes(1));
        $this->assertSame(['https://cdn.comercio.test/lampara.jpg'], $this->imagenes(3));
    }

    // ---------------------------------------------- sobre el catálogo anterior

    public function test_convierte_el_catalogo_anterior_sin_borrar_productos_ni_variaciones(): void
    {
        // La llave foránea de las variaciones debe estar activa para que esta prueba sirva.
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);

        $this->sembrarCatalogoAnterior();
        DB::table('producto_variaciones')->where('id_variacion', 9)->update(['disponible' => false]);
        DB::table('productos')->where('id_producto', 4)->update(['estado_producto' => 'desactivado']);

        $this->sembrar();

        foreach (self::ESCENARIO as $id => [$idProducto, $idComercio, $sku, $precio]) {
            $v = $this->variacionSembrada($id);

            $this->assertSame([$idProducto, $idComercio, $sku, $precio], [(int) $v->id_producto, (int) $v->id_comercio, $v->sku, (float) $v->precio]);
            $this->assertSame('2026-11-10 08:00:00', $v->created_at);
        }

        // Los productos 4, 6 y 8 cambiaron de comercio junto con sus variaciones.
        $this->assertSame(
            [4 => 2, 6 => 3, 8 => 4],
            DB::table('productos')->whereIn('id_producto', [4, 6, 8])->orderBy('id_producto')->pluck('id_comercio', 'id_producto')->map(fn ($c) => (int) $c)->all()
        );

        $this->assertFalse((bool) $this->variacionSembrada(9)->disponible, 'No reactiva una variación agotada.');
        $this->assertSame('desactivado', DB::table('productos')->where('id_producto', 4)->value('estado_producto'));
        $this->assertSame('2026-11-10 08:00:00', DB::table('productos')->where('id_producto', 8)->value('created_at'));
        $this->assertSame(self::TABLAS['productos'], DB::table('productos')->count());
        $this->assertSame(self::TABLAS['producto_variaciones'], DB::table('producto_variaciones')->count());
    }

    public function test_quita_los_atributos_del_producto_anterior_en_las_variaciones_reutilizadas(): void
    {
        $this->sembrarCatalogoAnterior();

        $this->sembrar();

        // La variación 9 era café en grano: pierde "Tueste" y su "Color" pasa a ser el de la botella.
        $this->assertSame(
            ['Capacidad' => '750 ml', 'Color' => 'Negro'],
            DB::table('atributos_variacion')->where('id_variacion', 9)->orderBy('tipo_atributo')->pluck('valor', 'tipo_atributo')->all()
        );
        $this->assertSame('32', DB::table('atributos_variacion')->where('id_variacion', 6)->where('tipo_atributo', 'Talla')->value('valor'));
        $this->assertSame(self::TABLAS['atributos_variacion'], DB::table('atributos_variacion')->count());
    }

    public function test_reemplaza_o_quita_solo_las_imagenes_sembradas_de_productos_que_cambiaron(): void
    {
        $this->sembrarCatalogoAnterior();

        $this->sembrar();

        $audifonos = 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?fm=jpg&w=600';

        $this->assertSame(['https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?fm=jpg&w=600'], $this->imagenes(1), 'El mouse conserva su imagen.');
        $this->assertSame([$audifonos], $this->imagenes(2), 'El teclado pasó a ser audífonos: recibe la imagen de audífonos.');
        $this->assertSame([], $this->imagenes(3), 'La lámpara no tiene imagen propia: se quita la de audífonos.');
        $this->assertSame([], $this->imagenes(4), 'Se quita la imagen de example.com de la cámara.');
        $this->assertSame(['https://cdn.comercio.test/foto-propia.jpg'], $this->imagenes(6), 'La imagen del comercio no se toca.');
        $this->assertSame(['https://images.unsplash.com/photo-1521572267360-ee0c2909d518?fm=jpg&w=600'], $this->imagenes(7));
        $this->assertSame([], $this->imagenes(8), 'La botella no hereda la imagen del café en grano.');
    }

    public function test_un_sku_usado_por_otra_variacion_detiene_el_seeder_y_dice_cuales_chocan(): void
    {
        DB::table('productos')->insert(['id_producto' => 900, 'id_comercio' => 4, 'id_categoria' => 1, 'nombre' => 'Botella del comercio']);
        DB::table('producto_variaciones')->insert([
            'id_variacion' => 900, 'id_producto' => 900, 'id_comercio' => 4, 'sku' => 'DP-BOT-TER-NEG', 'precio' => 5000, 'tarifa_impuesto' => 13,
        ]);

        try {
            $this->sembrar();
            $this->fail('Debió detenerse por el SKU repetido.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('la variación 9 no puede usar el SKU DP-BOT-TER-NEG en el comercio 4', $error->getMessage());
            $this->assertStringContainsString('ya lo tiene la variación 900', $error->getMessage());
        }

        // Todo va en una transacción: no queda nada a medias.
        $this->assertSame(1, DB::table('productos')->count());
        $this->assertSame(0, DB::table('categorias')->count());
    }
}
