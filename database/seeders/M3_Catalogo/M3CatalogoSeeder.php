<?php

declare(strict_types=1);

namespace Database\Seeders\M3_Catalogo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class M3CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // 1. Estado de los comercios del catálogo
            $comerciosEstado = [
                ['id_comercio' => 1, 'activo' => true],
                ['id_comercio' => 2, 'activo' => true],
                ['id_comercio' => 3, 'activo' => true],
                ['id_comercio' => 4, 'activo' => true],
                ['id_comercio' => 5, 'activo' => true],
            ];

            foreach ($comerciosEstado as $estado) {
                DB::table('estado_comercios_catalogo')->updateOrInsert(
                    ['id_comercio' => $estado['id_comercio']],
                    [
                        'activo' => $estado['activo'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            // 2. Categorías
            $categorias = [
                [
                    'id_categoria' => 1,
                    'id_categoria_padre' => null,
                    'nombre' => 'Tecnología y Oficina',
                    'descripcion' => 'Productos tecnológicos y artículos para oficina',
                ],
                [
                    'id_categoria' => 2,
                    'id_categoria_padre' => 1,
                    'nombre' => 'Accesorios y Artículos de Oficina',
                    'descripcion' => 'Accesorios tecnológicos y artículos de escritorio',
                ],
                [
                    'id_categoria' => 3,
                    'id_categoria_padre' => null,
                    'nombre' => 'Ropa y Vestimenta',
                    'descripcion' => 'Prendas de vestir y ropa deportiva',
                ],
                [
                    'id_categoria' => 4,
                    'id_categoria_padre' => 3,
                    'nombre' => 'Calzado Deportivo',
                    'descripcion' => 'Tenis y calzado deportivo',
                ],
                [
                    'id_categoria' => 5,
                    'id_categoria_padre' => null,
                    'nombre' => 'Alimentos y Bebidas',
                    'descripcion' => 'Productos alimenticios y artículos para bebidas',
                ],
                [
                    'id_categoria' => 6,
                    'id_categoria_padre' => 5,
                    'nombre' => 'Café',
                    'descripcion' => 'Café molido y productos relacionados',
                ],
            ];

            foreach ($categorias as $categoria) {
                DB::table('categorias')->updateOrInsert(
                    ['id_categoria' => $categoria['id_categoria']],
                    [
                        'id_categoria_padre' => $categoria['id_categoria_padre'],
                        'nombre' => $categoria['nombre'],
                        'descripcion' => $categoria['descripcion'],
                        'updated_at' => now(),
                    ]
                );
            }

            // 3. Marcas
            $marcas = [
                [
                    'id_marca' => 1,
                    'nombre' => 'Logitech',
                    'logotipo_url' => 'https://example.com/logitech.png',
                    'destacada' => true,
                ],
            ];

            foreach ($marcas as $marca) {
                DB::table('marcas')->updateOrInsert(
                    ['id_marca' => $marca['id_marca']],
                    [
                        'nombre' => $marca['nombre'],
                        'logotipo_url' => $marca['logotipo_url'],
                        'destacada' => $marca['destacada'],
                        'updated_at' => now(),
                    ]
                );
            }

            // 4. Productos de los escenarios del apartado 2.2
            //
            // Escenario 1:
            // Comercio 1: Soluciones del Milenio.
            // Comercio 2: Temulandia.
            // Comercio 3: Elegancia.
            //
            // Escenario 2:
            // Comercio 4: Deportes Pacífico.
            // Comercio 5: Café Volcán.
            //
            // Se reutilizan los identificadores de productos 1 al 10.

            $productos = [
                [
                    'id_producto' => 1,
                    'id_comercio' => 1,
                    'id_categoria' => 2,
                    'id_marca' => 1,
                    'nombre' => 'Mouse Logitech MQ291 negro',
                    'descripcion' => 'Mouse Logitech MQ291 de color negro',
                    'video_url' => null,
                    'destacado' => true,
                    'estado_producto' => 'publicado',
                ],
                [
                    'id_producto' => 2,
                    'id_comercio' => 1,
                    'id_categoria' => 2,
                    'id_marca' => null,
                    'nombre' => 'Audífonos Bluetooth negros',
                    'descripcion' => 'Audífonos inalámbricos Bluetooth de color negro',
                    'video_url' => null,
                    'destacado' => false,
                    'estado_producto' => 'publicado',
                ],
                [
                    'id_producto' => 3,
                    'id_comercio' => 2,
                    'id_categoria' => 1,
                    'id_marca' => null,
                    'nombre' => 'Lámpara LED de escritorio blanca',
                    'descripcion' => 'Lámpara LED blanca para escritorio',
                    'video_url' => null,
                    'destacado' => false,
                    'estado_producto' => 'publicado',
                ],
                [
                    'id_producto' => 4,
                    'id_comercio' => 2,
                    'id_categoria' => 1,
                    'id_marca' => null,
                    'nombre' => 'Organizador de escritorio gris',
                    'descripcion' => 'Organizador de escritorio de color gris',
                    'video_url' => null,
                    'destacado' => false,
                    'estado_producto' => 'publicado',
                ],
                [
                    'id_producto' => 5,
                    'id_comercio' => 3,
                    'id_categoria' => 3,
                    'id_marca' => null,
                    'nombre' => 'Camisa de lino manga larga',
                    'descripcion' => 'Camisa de lino de manga larga, talla M, color azul',
                    'video_url' => null,
                    'destacado' => true,
                    'estado_producto' => 'publicado',
                ],
                [
                    'id_producto' => 6,
                    'id_comercio' => 3,
                    'id_categoria' => 3,
                    'id_marca' => null,
                    'nombre' => 'Pantalón chino',
                    'descripcion' => 'Pantalón chino de talla 32, color beige',
                    'video_url' => null,
                    'destacado' => false,
                    'estado_producto' => 'publicado',
                ],
                [
                    'id_producto' => 7,
                    'id_comercio' => 4,
                    'id_categoria' => 3,
                    'id_marca' => null,
                    'nombre' => 'Camiseta deportiva',
                    'descripcion' => 'Camiseta deportiva disponible en tallas S y XL',
                    'video_url' => null,
                    'destacado' => true,
                    'estado_producto' => 'publicado',
                ],
                [
                    'id_producto' => 8,
                    'id_comercio' => 4,
                    'id_categoria' => 5,
                    'id_marca' => null,
                    'nombre' => 'Botella térmica negra 750 ml',
                    'descripcion' => 'Botella térmica negra con capacidad de 750 ml',
                    'video_url' => null,
                    'destacado' => false,
                    'estado_producto' => 'publicado',
                ],
                [
                    'id_producto' => 9,
                    'id_comercio' => 5,
                    'id_categoria' => 6,
                    'id_marca' => null,
                    'nombre' => 'Café molido 340 g',
                    'descripcion' => 'Café molido de 340 gramos, tueste medio',
                    'video_url' => null,
                    'destacado' => true,
                    'estado_producto' => 'publicado',
                ],
                [
                    'id_producto' => 10,
                    'id_comercio' => 5,
                    'id_categoria' => 5,
                    'id_marca' => null,
                    'nombre' => 'Taza cerámica blanca',
                    'descripcion' => 'Taza de cerámica de color blanco',
                    'video_url' => null,
                    'destacado' => false,
                    'estado_producto' => 'publicado',
                ],
            ];

            foreach ($productos as $producto) {
                DB::table('productos')->updateOrInsert(
                    ['id_producto' => $producto['id_producto']],
                    [
                        'id_comercio' => $producto['id_comercio'],
                        'id_categoria' => $producto['id_categoria'],
                        'id_marca' => $producto['id_marca'],
                        'nombre' => $producto['nombre'],
                        'descripcion' => $producto['descripcion'],
                        'video_url' => $producto['video_url'],
                        'destacado' => $producto['destacado'],
                        'estado_producto' => $producto['estado_producto'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // 5. Variaciones de productos
            //
            // Se reutilizan los identificadores 1 al 11.
            // Los precios corresponden a los precios base del apartado 2.2.

            $variaciones = [
                [
                    'id_variacion' => 1,
                    'id_producto' => 1,
                    'id_comercio' => 1,
                    'sku' => 'SDM-LOG-MQ291-NEG',
                    'precio' => 10000.00,
                    'tarifa_impuesto' => 13.00,
                    'disponible' => true,
                ],
                [
                    'id_variacion' => 2,
                    'id_producto' => 2,
                    'id_comercio' => 1,
                    'sku' => 'SDM-AUD-BT-NEG',
                    'precio' => 15000.00,
                    'tarifa_impuesto' => 13.00,
                    'disponible' => true,
                ],
                [
                    'id_variacion' => 3,
                    'id_producto' => 3,
                    'id_comercio' => 2,
                    'sku' => 'TML-LAMP-LED-BLA',
                    'precio' => 4500.00,
                    'tarifa_impuesto' => 13.00,
                    'disponible' => true,
                ],
                [
                    'id_variacion' => 4,
                    'id_producto' => 4,
                    'id_comercio' => 2,
                    'sku' => 'TML-ORG-ESC-GRI',
                    'precio' => 3500.00,
                    'tarifa_impuesto' => 13.00,
                    'disponible' => true,
                ],
                [
                    'id_variacion' => 5,
                    'id_producto' => 5,
                    'id_comercio' => 3,
                    'sku' => 'ELG-CAM-LIN-M-AZU',
                    'precio' => 25000.00,
                    'tarifa_impuesto' => 13.00,
                    'disponible' => true,
                ],
                [
                    'id_variacion' => 6,
                    'id_producto' => 6,
                    'id_comercio' => 3,
                    'sku' => 'ELG-PAN-CHI-32-BEI',
                    'precio' => 30000.00,
                    'tarifa_impuesto' => 13.00,
                    'disponible' => true,
                ],
                [
                    'id_variacion' => 7,
                    'id_producto' => 7,
                    'id_comercio' => 4,
                    'sku' => 'DP-CAM-DEP-S',
                    'precio' => 8990.00,
                    'tarifa_impuesto' => 13.00,
                    'disponible' => true,
                ],
                [
                    'id_variacion' => 8,
                    'id_producto' => 7,
                    'id_comercio' => 4,
                    'sku' => 'DP-CAM-DEP-XL',
                    'precio' => 9490.00,
                    'tarifa_impuesto' => 13.00,
                    'disponible' => true,
                ],
                [
                    'id_variacion' => 9,
                    'id_producto' => 8,
                    'id_comercio' => 4,
                    'sku' => 'DP-BOT-TER-NEG',
                    'precio' => 6750.00,
                    'tarifa_impuesto' => 13.00,
                    'disponible' => true,
                ],
                [
                    'id_variacion' => 10,
                    'id_producto' => 9,
                    'id_comercio' => 5,
                    'sku' => 'CV-CAF-340-MED',
                    'precio' => 5425.00,
                    'tarifa_impuesto' => 1.00,
                    'disponible' => true,
                ],
                [
                    'id_variacion' => 11,
                    'id_producto' => 10,
                    'id_comercio' => 5,
                    'sku' => 'CV-TAZ-CER-BLA',
                    'precio' => 3333.00,
                    'tarifa_impuesto' => 13.00,
                    'disponible' => true,
                ],
            ];

            foreach ($variaciones as $variacion) {
                DB::table('producto_variaciones')->updateOrInsert(
                    ['id_variacion' => $variacion['id_variacion']],
                    [
                        'id_producto' => $variacion['id_producto'],
                        'id_comercio' => $variacion['id_comercio'],
                        'sku' => $variacion['sku'],
                        'precio' => $variacion['precio'],
                        'tarifa_impuesto' => $variacion['tarifa_impuesto'],
                        'disponible' => $variacion['disponible'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // 6. Atributos de las variaciones
            //
            // Se eliminan únicamente los atributos anteriores de las
            // variaciones 1 al 11 para evitar conservar atributos incorrectos.

            DB::table('atributos_variacion')
                ->whereIn('id_variacion', range(1, 11))
                ->delete();

            $atributos = [
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

            foreach ($atributos as $atributo) {
                DB::table('atributos_variacion')->updateOrInsert(
                    [
                        'id_variacion' => $atributo['id_variacion'],
                        'tipo_atributo' => $atributo['tipo_atributo'],
                    ],
                    [
                        'valor' => $atributo['valor'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // 7. Imágenes de productos
            //
            // Las imágenes anteriores de los productos 1 al 10
            // correspondían a productos que ya no representan los escenarios.
            // Se eliminan para evitar mostrar imágenes equivocadas.
            //
            // Se conservan intactas las imágenes de productos con otros IDs.

            DB::table('imagenes_producto')
                ->whereIn('id_producto', range(1, 10))
                ->delete();
        });

        // 8. Sincronizar las secuencias de PostgreSQL.
        // Se mantienen los IDs existentes y se evita que las próximas
        // inserciones automáticas colisionen con los identificadores usados.

        DB::statement("
            SELECT setval(
                pg_get_serial_sequence('productos', 'id_producto'),
                (SELECT MAX(id_producto) FROM productos)
            )
        ");

        DB::statement("
            SELECT setval(
                pg_get_serial_sequence('producto_variaciones', 'id_variacion'),
                (SELECT MAX(id_variacion) FROM producto_variaciones)
            )
        ");
    }
}
