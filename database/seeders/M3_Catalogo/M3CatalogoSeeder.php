<?php

declare(strict_types=1);

namespace Database\Seeders\M3_Catalogo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Catálogo de los escenarios de demostración (sección 2.2 del colectivo del PC4).
 *
 * Los comercios 1 a 5 son los de ComerciosDemoSeeder (M2), que debe ejecutarse antes.
 * Los ids son fijos porque M4 y M5 los usan en sus propios seeders: productos 1 a 10 con
 * las variaciones 1 a 11 (escenarios), y productos 301 y 401 con las variaciones 501, 502
 * y 601 (demostración de inventario de M4).
 *
 * Se puede ejecutar varias veces sobre una base con datos:
 *  - No borra productos, variaciones ni categorías.
 *  - Una fila que ya existe conserva su created_at; solo cambia updated_at.
 *  - Nunca cambia `disponible` de una variación existente (lo gobiernan los eventos de M4)
 *    ni `estado_producto` de un producto existente (lo gobierna el comercio).
 *  - No pisa el estado de un comercio que ya esté en estado_comercios_catalogo.
 *  - No toca las imágenes que haya puesto un comercio.
 *
 *     php artisan db:seed --class="Database\Seeders\M3_Catalogo\M3CatalogoSeeder"
 */
class M3CatalogoSeeder extends Seeder
{
    public const COMERCIOS = [1, 2, 3, 4, 5];

    /** @var list<array{id_categoria: int, id_categoria_padre: ?int, nombre: string, descripcion: string}> */
    public const CATEGORIAS = [
        ['id_categoria' => 1, 'id_categoria_padre' => null, 'nombre' => 'Tecnología y Oficina', 'descripcion' => 'Productos tecnológicos y artículos para oficina'],
        ['id_categoria' => 2, 'id_categoria_padre' => 1, 'nombre' => 'Accesorios y Artículos de Oficina', 'descripcion' => 'Accesorios tecnológicos y artículos de escritorio'],
        ['id_categoria' => 3, 'id_categoria_padre' => null, 'nombre' => 'Ropa y Vestimenta', 'descripcion' => 'Prendas de vestir y ropa deportiva'],
        ['id_categoria' => 4, 'id_categoria_padre' => 3, 'nombre' => 'Calzado Deportivo', 'descripcion' => 'Tenis y calzado deportivo'],
        ['id_categoria' => 5, 'id_categoria_padre' => null, 'nombre' => 'Alimentos y Bebidas', 'descripcion' => 'Productos alimenticios y artículos para bebidas'],
        ['id_categoria' => 6, 'id_categoria_padre' => 5, 'nombre' => 'Café', 'descripcion' => 'Café molido y productos relacionados'],
    ];

    /** @var list<array{id_marca: int, nombre: string, logotipo_url: ?string, destacada: bool}> */
    public const MARCAS = [
        ['id_marca' => 1, 'nombre' => 'Logitech', 'logotipo_url' => null, 'destacada' => true],
    ];

    /**
     * Escenario 1: comercios 1 (Soluciones del Milenio), 2 (Temulandia) y 3 (Elegancia).
     * Escenario 2: comercios 4 (Deportes Pacífico) y 5 (Café Volcán).
     *
     * @var list<array<string, mixed>>
     */
    public const PRODUCTOS = [
        ['id_producto' => 1, 'id_comercio' => 1, 'id_categoria' => 2, 'id_marca' => 1, 'nombre' => 'Mouse Logitech MQ291 negro', 'descripcion' => 'Mouse Logitech MQ291 de color negro', 'destacado' => true],
        ['id_producto' => 2, 'id_comercio' => 1, 'id_categoria' => 2, 'id_marca' => null, 'nombre' => 'Audífonos Bluetooth negros', 'descripcion' => 'Audífonos inalámbricos Bluetooth de color negro', 'destacado' => false],
        ['id_producto' => 3, 'id_comercio' => 2, 'id_categoria' => 1, 'id_marca' => null, 'nombre' => 'Lámpara LED de escritorio blanca', 'descripcion' => 'Lámpara LED blanca para escritorio', 'destacado' => false],
        ['id_producto' => 4, 'id_comercio' => 2, 'id_categoria' => 1, 'id_marca' => null, 'nombre' => 'Organizador de escritorio gris', 'descripcion' => 'Organizador de escritorio de color gris', 'destacado' => false],
        ['id_producto' => 5, 'id_comercio' => 3, 'id_categoria' => 3, 'id_marca' => null, 'nombre' => 'Camisa de lino manga larga', 'descripcion' => 'Camisa de lino de manga larga, talla M, color azul', 'destacado' => true],
        ['id_producto' => 6, 'id_comercio' => 3, 'id_categoria' => 3, 'id_marca' => null, 'nombre' => 'Pantalón chino', 'descripcion' => 'Pantalón chino de talla 32, color beige', 'destacado' => false],
        ['id_producto' => 7, 'id_comercio' => 4, 'id_categoria' => 3, 'id_marca' => null, 'nombre' => 'Camiseta deportiva', 'descripcion' => 'Camiseta deportiva disponible en tallas S y XL', 'destacado' => true],
        ['id_producto' => 8, 'id_comercio' => 4, 'id_categoria' => 5, 'id_marca' => null, 'nombre' => 'Botella térmica negra 750 ml', 'descripcion' => 'Botella térmica negra con capacidad de 750 ml', 'destacado' => false],
        ['id_producto' => 9, 'id_comercio' => 5, 'id_categoria' => 6, 'id_marca' => null, 'nombre' => 'Café molido 340 g', 'descripcion' => 'Café molido de 340 gramos, tueste medio', 'destacado' => true],
        ['id_producto' => 10, 'id_comercio' => 5, 'id_categoria' => 5, 'id_marca' => null, 'nombre' => 'Taza cerámica blanca', 'descripcion' => 'Taza de cerámica de color blanco', 'destacado' => false],
        // Demostración de inventario de M4 (InventarioDemoSeeder).
        ['id_producto' => 301, 'id_comercio' => 1, 'id_categoria' => 2, 'id_marca' => 1, 'nombre' => 'Monitor LED Full HD 24 pulgadas', 'descripcion' => 'Monitor LED Full HD para trabajo y entretenimiento', 'destacado' => true],
        ['id_producto' => 401, 'id_comercio' => 2, 'id_categoria' => 2, 'id_marca' => null, 'nombre' => 'Laptop Lenovo IdeaPad', 'descripcion' => 'Computadora portátil para productividad y trabajo', 'destacado' => true],
    ];

    /**
     * Precios base de la sección 2.2.
     *
     * @var list<array{id_variacion: int, id_producto: int, id_comercio: int, sku: string, precio: float, tarifa_impuesto: float}>
     */
    public const VARIACIONES = [
        ['id_variacion' => 1, 'id_producto' => 1, 'id_comercio' => 1, 'sku' => 'SDM-LOG-MQ291-NEG', 'precio' => 10000.00, 'tarifa_impuesto' => 13.00],
        ['id_variacion' => 2, 'id_producto' => 2, 'id_comercio' => 1, 'sku' => 'SDM-AUD-BT-NEG', 'precio' => 15000.00, 'tarifa_impuesto' => 13.00],
        ['id_variacion' => 3, 'id_producto' => 3, 'id_comercio' => 2, 'sku' => 'TML-LAMP-LED-BLA', 'precio' => 4500.00, 'tarifa_impuesto' => 13.00],
        ['id_variacion' => 4, 'id_producto' => 4, 'id_comercio' => 2, 'sku' => 'TML-ORG-ESC-GRI', 'precio' => 3500.00, 'tarifa_impuesto' => 13.00],
        ['id_variacion' => 5, 'id_producto' => 5, 'id_comercio' => 3, 'sku' => 'ELG-CAM-LIN-M-AZU', 'precio' => 25000.00, 'tarifa_impuesto' => 13.00],
        ['id_variacion' => 6, 'id_producto' => 6, 'id_comercio' => 3, 'sku' => 'ELG-PAN-CHI-32-BEI', 'precio' => 30000.00, 'tarifa_impuesto' => 13.00],
        ['id_variacion' => 7, 'id_producto' => 7, 'id_comercio' => 4, 'sku' => 'DP-CAM-DEP-S', 'precio' => 8990.00, 'tarifa_impuesto' => 13.00],
        ['id_variacion' => 8, 'id_producto' => 7, 'id_comercio' => 4, 'sku' => 'DP-CAM-DEP-XL', 'precio' => 9490.00, 'tarifa_impuesto' => 13.00],
        ['id_variacion' => 9, 'id_producto' => 8, 'id_comercio' => 4, 'sku' => 'DP-BOT-TER-NEG', 'precio' => 6750.00, 'tarifa_impuesto' => 13.00],
        ['id_variacion' => 10, 'id_producto' => 9, 'id_comercio' => 5, 'sku' => 'CV-CAF-340-MED', 'precio' => 5425.00, 'tarifa_impuesto' => 1.00],
        ['id_variacion' => 11, 'id_producto' => 10, 'id_comercio' => 5, 'sku' => 'CV-TAZ-CER-BLA', 'precio' => 3333.00, 'tarifa_impuesto' => 13.00],
        ['id_variacion' => 501, 'id_producto' => 301, 'id_comercio' => 1, 'sku' => 'SDM-MON-FHD-24', 'precio' => 9500.00, 'tarifa_impuesto' => 13.00],
        ['id_variacion' => 502, 'id_producto' => 301, 'id_comercio' => 1, 'sku' => 'SDM-MON-FHD-24-PRO', 'precio' => 10000.00, 'tarifa_impuesto' => 13.00],
        ['id_variacion' => 601, 'id_producto' => 401, 'id_comercio' => 2, 'sku' => 'TE-LEN-IDP-01', 'precio' => 25000.00, 'tarifa_impuesto' => 13.00],
    ];

    /** @var list<array{id_variacion: int, tipo_atributo: string, valor: string}> */
    public const ATRIBUTOS = [
        ['id_variacion' => 1, 'tipo_atributo' => 'Color', 'valor' => 'Negro'],
        ['id_variacion' => 2, 'tipo_atributo' => 'Color', 'valor' => 'Negro'],
        ['id_variacion' => 3, 'tipo_atributo' => 'Color', 'valor' => 'Blanco'],
        ['id_variacion' => 4, 'tipo_atributo' => 'Color', 'valor' => 'Gris'],
        ['id_variacion' => 5, 'tipo_atributo' => 'Talla', 'valor' => 'M'],
        ['id_variacion' => 5, 'tipo_atributo' => 'Color', 'valor' => 'Azul'],
        ['id_variacion' => 6, 'tipo_atributo' => 'Talla', 'valor' => '32'],
        ['id_variacion' => 6, 'tipo_atributo' => 'Color', 'valor' => 'Beige'],
        ['id_variacion' => 7, 'tipo_atributo' => 'Talla', 'valor' => 'S'],
        ['id_variacion' => 8, 'tipo_atributo' => 'Talla', 'valor' => 'XL'],
        ['id_variacion' => 9, 'tipo_atributo' => 'Color', 'valor' => 'Negro'],
        ['id_variacion' => 9, 'tipo_atributo' => 'Capacidad', 'valor' => '750 ml'],
        ['id_variacion' => 10, 'tipo_atributo' => 'Presentación', 'valor' => 'Molido'],
        ['id_variacion' => 10, 'tipo_atributo' => 'Peso', 'valor' => '340 g'],
        ['id_variacion' => 10, 'tipo_atributo' => 'Tueste', 'valor' => 'Medio'],
        ['id_variacion' => 11, 'tipo_atributo' => 'Color', 'valor' => 'Blanco'],
    ];

    /** Variaciones de los escenarios: sus ids se reutilizaron con otros productos. */
    public const VARIACIONES_DE_ESCENARIO = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];

    /**
     * Imagen principal (orden 1) por producto. Solo hay imagen donde ya existía en el
     * repositorio una URL que corresponde al producto; los demás quedan sin imagen.
     *
     * @var array<int, array{id_variacion: int, imagen_url: string, tamano_bytes: int}>
     */
    public const IMAGENES = [
        1 => ['id_variacion' => 1, 'imagen_url' => 'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?fm=jpg&w=600', 'tamano_bytes' => 150000],
        2 => ['id_variacion' => 2, 'imagen_url' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?fm=jpg&w=600', 'tamano_bytes' => 180000],
        7 => ['id_variacion' => 7, 'imagen_url' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?fm=jpg&w=600', 'tamano_bytes' => 140000],
        9 => ['id_variacion' => 10, 'imagen_url' => 'https://images.unsplash.com/photo-1559056199-641a0ac8b55e?fm=jpg&w=600', 'tamano_bytes' => 230000],
        10 => ['id_variacion' => 11, 'imagen_url' => 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?fm=jpg&w=600', 'tamano_bytes' => 120000],
    ];

    /** Productos cuyo id pasó a ser otro producto: su imagen sembrada anterior ya no corresponde. */
    public const PRODUCTOS_QUE_CAMBIARON = [2, 3, 4, 5, 6, 8];

    /** URL de Unsplash que sembraron versiones anteriores de este seeder (las de example.com se reconocen por el dominio). */
    private const IMAGENES_SEMBRADAS_ANTES = [
        'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?fm=jpg&w=600',
        'https://images.unsplash.com/photo-1587829741301-dc798b83add3?fm=jpg&w=600',
        'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?fm=jpg&w=600',
        'https://images.unsplash.com/photo-1588702547919-26089e690ecc?fm=jpg&w=600',
        'https://images.unsplash.com/photo-1617005082133-548c4dd27f35?fm=jpg&w=600',
        'https://images.unsplash.com/photo-1542291026-7eec264c27ff?fm=jpg&w=600',
        'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?fm=jpg&w=600',
        'https://images.unsplash.com/photo-1587734195503-904fca47e0e9?fm=jpg&w=600',
        'https://images.unsplash.com/photo-1559056199-641a0ac8b55e?fm=jpg&w=600',
        'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?fm=jpg&w=600',
    ];

    private const SKU_TEMPORAL = 'M3-SEMBRADO-TMP-';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->sembrarEstadoDeComercios();
            $this->sembrarCategorias();
            $this->sembrarMarcas();

            $this->exigirSkuLibres();
            $this->liberarVariacionesAntesDeMoverlas();
            $this->sembrarProductos();
            $this->sembrarVariaciones();

            $this->sembrarAtributos();
            $this->sembrarImagenes();
            $this->sincronizarSecuencias();
        });
    }

    /** Solo crea el estado de los comercios que falten: no pisa una suspensión que haya llegado de M2. */
    private function sembrarEstadoDeComercios(): void
    {
        foreach (self::COMERCIOS as $idComercio) {
            DB::table('estado_comercios_catalogo')->insertOrIgnore([
                'id_comercio' => $idComercio,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function sembrarCategorias(): void
    {
        foreach (self::CATEGORIAS as $categoria) {
            $this->guardar('categorias', ['id_categoria' => $categoria['id_categoria']], [
                'id_categoria_padre' => $categoria['id_categoria_padre'],
                'nombre' => $categoria['nombre'],
                'descripcion' => $categoria['descripcion'],
            ]);
        }
    }

    private function sembrarMarcas(): void
    {
        foreach (self::MARCAS as $marca) {
            $this->guardar('marcas', ['id_marca' => $marca['id_marca']], [
                'nombre' => $marca['nombre'],
                'logotipo_url' => $marca['logotipo_url'],
                'destacada' => $marca['destacada'],
            ]);
        }
    }

    /** En un producto existente no se toca estado_producto: solo se fija al crearlo. */
    private function sembrarProductos(): void
    {
        foreach (self::PRODUCTOS as $producto) {
            $this->guardar('productos', ['id_producto' => $producto['id_producto']], [
                'id_comercio' => $producto['id_comercio'],
                'id_categoria' => $producto['id_categoria'],
                'id_marca' => $producto['id_marca'],
                'nombre' => $producto['nombre'],
                'descripcion' => $producto['descripcion'],
                'destacado' => $producto['destacado'],
            ], ['video_url' => null, 'estado_producto' => 'publicado']);
        }
    }

    /** En una variación existente no se toca `disponible`: solo se fija al crearla. */
    private function sembrarVariaciones(): void
    {
        foreach (self::VARIACIONES as $variacion) {
            $this->guardar('producto_variaciones', ['id_variacion' => $variacion['id_variacion']], [
                'id_producto' => $variacion['id_producto'],
                'id_comercio' => $variacion['id_comercio'],
                'sku' => $variacion['sku'],
                'precio' => $variacion['precio'],
                'tarifa_impuesto' => $variacion['tarifa_impuesto'],
            ], ['disponible' => true]);
        }
    }

    /**
     * UNIQUE (id_comercio, sku): si una variación que no es de este seeder ya usa uno de los
     * SKU, se detiene antes de escribir variaciones y dice cuáles chocan.
     */
    private function exigirSkuLibres(): void
    {
        $propias = array_column(self::VARIACIONES, 'id_variacion');
        $choques = [];

        foreach (self::VARIACIONES as $variacion) {
            $ajenas = DB::table('producto_variaciones')
                ->where('id_comercio', $variacion['id_comercio'])
                ->where('sku', $variacion['sku'])
                ->whereNotIn('id_variacion', $propias)
                ->pluck('id_variacion');

            foreach ($ajenas as $idAjena) {
                $choques[] = "la variación {$variacion['id_variacion']} no puede usar el SKU {$variacion['sku']} "
                    ."en el comercio {$variacion['id_comercio']}: ya lo tiene la variación {$idAjena}";
            }
        }

        if ($choques !== []) {
            throw new RuntimeException('M3CatalogoSeeder: SKU repetido; '.implode('; ', $choques).'.');
        }
    }

    /**
     * Prepara las variaciones existentes para poder cambiarlas sin romper restricciones.
     *
     * Dos restricciones de producto_variaciones impiden actualizar fila por fila:
     *  - La llave foránea (id_producto, id_comercio) hacia productos: un producto que cambia
     *    de comercio (por ejemplo el 8, del comercio 5 al 4) no se puede actualizar mientras
     *    sus variaciones apunten al par anterior. Esas variaciones se mueven primero a un
     *    producto del seeder que no cambia de comercio y luego sembrarVariaciones() las deja
     *    en su producto definitivo.
     *  - UNIQUE (id_comercio, sku): dos variaciones del seeder podrían intercambiar SKU. Las
     *    que van a cambiar reciben antes un SKU temporal.
     */
    private function liberarVariacionesAntesDeMoverlas(): void
    {
        $definidas = collect(self::VARIACIONES)->keyBy('id_variacion');
        $productosActuales = DB::table('productos')
            ->whereIn('id_producto', array_column(self::PRODUCTOS, 'id_producto'))
            ->get()
            ->keyBy('id_producto');

        $cambianDeComercio = [];
        $refugio = null;

        foreach (self::PRODUCTOS as $producto) {
            $actual = $productosActuales->get($producto['id_producto']);

            if ($actual === null) {
                continue;
            }

            if ((int) $actual->id_comercio !== $producto['id_comercio']) {
                $cambianDeComercio[] = $producto['id_producto'];
            } elseif ($refugio === null) {
                $refugio = $actual;
            }
        }

        if ($cambianDeComercio !== []) {
            $this->exigirQueSePuedanCambiarDeComercio($cambianDeComercio, $definidas->keys()->all());

            if ($refugio === null) {
                throw new RuntimeException(
                    'M3CatalogoSeeder: los productos '.implode(', ', $cambianDeComercio).' cambian de comercio y no hay '
                    .'ningún producto del seeder que conserve el suyo para mover sus variaciones. Revisa los datos a mano.'
                );
            }
        }

        $existentes = DB::table('producto_variaciones')
            ->whereIn('id_variacion', $definidas->keys()->all())
            ->get();

        foreach ($existentes as $fila) {
            $definida = $definidas->get($fila->id_variacion);
            $enProductoQueCambia = in_array((int) $fila->id_producto, $cambianDeComercio, true);
            $cambia = (int) $fila->id_producto !== $definida['id_producto']
                || (int) $fila->id_comercio !== $definida['id_comercio']
                || $fila->sku !== $definida['sku'];

            if (! $cambia && ! $enProductoQueCambia) {
                continue;
            }

            $cambios = ['sku' => self::SKU_TEMPORAL.$fila->id_variacion];

            if ($enProductoQueCambia) {
                $cambios['id_producto'] = $refugio->id_producto;
                $cambios['id_comercio'] = $refugio->id_comercio;
            }

            DB::table('producto_variaciones')->where('id_variacion', $fila->id_variacion)->update($cambios);
        }
    }

    /**
     * Un producto solo se cambia de comercio si todo lo que cuelga de él es de este seeder.
     *
     * @param  list<int>  $idsProducto
     * @param  list<int>  $variacionesPropias
     */
    private function exigirQueSePuedanCambiarDeComercio(array $idsProducto, array $variacionesPropias): void
    {
        $ajenas = DB::table('producto_variaciones')
            ->whereIn('id_producto', $idsProducto)
            ->whereNotIn('id_variacion', $variacionesPropias)
            ->get(['id_variacion', 'id_producto']);

        if ($ajenas->isNotEmpty()) {
            $detalle = $ajenas->map(fn ($v): string => "variación {$v->id_variacion} del producto {$v->id_producto}")->implode(', ');

            throw new RuntimeException(
                "M3CatalogoSeeder: no se puede cambiar de comercio un producto con variaciones que no son del seeder ({$detalle})."
            );
        }

        $relacionados = DB::table('productos_relacionados')
            ->whereIn('id_producto', $idsProducto)
            ->orWhereIn('id_producto_relacionado', $idsProducto)
            ->count();

        if ($relacionados > 0) {
            throw new RuntimeException(
                'M3CatalogoSeeder: los productos '.implode(', ', $idsProducto).' cambian de comercio y tienen productos '
                .'relacionados (deben ser del mismo comercio). Quita esas relaciones antes de sembrar.'
            );
        }
    }

    /**
     * Upsert por (variación, tipo).
     *
     * Único borrado del seeder: en las variaciones 1 a 11, cuyos ids se reutilizaron con otros
     * productos, se quitan los atributos cuyo tipo ya no está en la definición nueva. Así la
     * variación 9, que era café en grano con "Tueste", no conserva ese atributo al pasar a ser
     * la botella térmica. Las demás variaciones no pierden ningún atributo.
     */
    private function sembrarAtributos(): void
    {
        foreach (self::ATRIBUTOS as $atributo) {
            $this->guardar('atributos_variacion', [
                'id_variacion' => $atributo['id_variacion'],
                'tipo_atributo' => $atributo['tipo_atributo'],
            ], ['valor' => $atributo['valor']]);
        }

        $tiposPorVariacion = collect(self::ATRIBUTOS)->groupBy('id_variacion');

        foreach (self::VARIACIONES_DE_ESCENARIO as $idVariacion) {
            DB::table('atributos_variacion')
                ->where('id_variacion', $idVariacion)
                ->whereNotIn('tipo_atributo', $tiposPorVariacion->get($idVariacion, collect())->pluck('tipo_atributo')->all())
                ->delete();
        }
    }

    /**
     * Imágenes: nunca se toca una imagen que no haya puesto una versión anterior de este seeder.
     *
     *  - Producto sin ninguna imagen: recibe la suya si está en IMAGENES.
     *  - Producto que cambió de significado y conserva una imagen sembrada del producto anterior:
     *    se reemplaza por la suya si está en IMAGENES; si no hay, se borra esa fila (y solo esa).
     *  - Todo lo demás se deja como está.
     */
    private function sembrarImagenes(): void
    {
        foreach (array_column(self::PRODUCTOS, 'id_producto') as $idProducto) {
            $nueva = self::IMAGENES[$idProducto] ?? null;
            $actuales = DB::table('imagenes_producto')->where('id_producto', $idProducto)->get();

            if ($actuales->isEmpty()) {
                if ($nueva !== null) {
                    DB::table('imagenes_producto')->insert($this->datosDeImagen($nueva) + [
                        'id_producto' => $idProducto,
                        'orden' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                continue;
            }

            if (! in_array($idProducto, self::PRODUCTOS_QUE_CAMBIARON, true)) {
                continue;
            }

            $yaTieneLaNueva = $nueva !== null && $actuales->contains('imagen_url', $nueva['imagen_url']);

            foreach ($actuales as $imagen) {
                if (! $this->esImagenSembrada($imagen->imagen_url) || ($nueva !== null && $imagen->imagen_url === $nueva['imagen_url'])) {
                    continue;
                }

                if ($nueva !== null && ! $yaTieneLaNueva) {
                    DB::table('imagenes_producto')
                        ->where('id_imagen', $imagen->id_imagen)
                        ->update($this->datosDeImagen($nueva) + ['updated_at' => now()]);
                    $yaTieneLaNueva = true;
                } else {
                    DB::table('imagenes_producto')->where('id_imagen', $imagen->id_imagen)->delete();
                }
            }
        }
    }

    /**
     * @param  array{id_variacion: int, imagen_url: string, tamano_bytes: int}  $imagen
     * @return array<string, mixed>
     */
    private function datosDeImagen(array $imagen): array
    {
        return [
            'id_variacion' => $imagen['id_variacion'],
            'imagen_url' => $imagen['imagen_url'],
            'formato' => 'jpg',
            'tamano_bytes' => $imagen['tamano_bytes'],
        ];
    }

    private function esImagenSembrada(string $url): bool
    {
        return in_array($url, self::IMAGENES_SEMBRADAS_ANTES, true)
            || in_array(parse_url($url, PHP_URL_HOST), ['example.com', 'www.example.com'], true);
    }

    /**
     * Como se insertan ids fijos, la secuencia de PostgreSQL puede quedar por detrás del mayor
     * id y la siguiente alta chocaría. Solo se adelanta, nunca se retrocede, y no hace nada en
     * otros motores (SQLite en las pruebas) ni con la tabla vacía.
     */
    private function sincronizarSecuencias(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tablas = [
            'categorias' => 'id_categoria',
            'marcas' => 'id_marca',
            'productos' => 'id_producto',
            'producto_variaciones' => 'id_variacion',
        ];

        foreach ($tablas as $tabla => $columna) {
            $maximo = DB::table($tabla)->max($columna);
            $secuencia = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS nombre', [$tabla, $columna])->nombre ?? null;

            if ($maximo === null || $secuencia === null) {
                continue;
            }

            // $secuencia viene de PostgreSQL, ya con esquema y comillas si las necesita.
            $estado = DB::selectOne("SELECT last_value, is_called FROM {$secuencia}");
            $ultimoEntregado = $estado->is_called ? (int) $estado->last_value : (int) $estado->last_value - 1;

            if ((int) $maximo > $ultimoEntregado) {
                DB::statement('SELECT setval(?::regclass, ?, true)', [$secuencia, (int) $maximo]);
            }
        }
    }

    /**
     * Crea la fila o actualiza la existente. Al actualizar conserva created_at y no toca las
     * columnas de $soloAlCrear.
     *
     * @param  array<string, mixed>  $clave
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $soloAlCrear
     */
    private function guardar(string $tabla, array $clave, array $datos, array $soloAlCrear = []): void
    {
        if (DB::table($tabla)->where($clave)->exists()) {
            DB::table($tabla)->where($clave)->update($datos + ['updated_at' => now()]);

            return;
        }

        DB::table($tabla)->insert($clave + $datos + $soloAlCrear + ['created_at' => now(), 'updated_at' => now()]);
    }
}
