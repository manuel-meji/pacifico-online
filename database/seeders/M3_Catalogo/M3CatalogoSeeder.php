<?php

declare(strict_types=1);

namespace Database\Seeders\M3_Catalogo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catalogo de los escenarios de demostracion (seccion 2.2 del colectivo del PC4):
 * Seembrado de categorias, marcas, productos, variaciones, atributos e imagenes
 * para que M4 y M5 los referencien en sus propios seeders. Los comercios 1 a 5 son los de ComerciosDemoSeeder (M2).
 */
class M3CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Estado de comercios (Proyección local M3)
            $comerciosEstado = [
                ['id_comercio' => 1, 'activo' => true],
                ['id_comercio' => 2, 'activo' => true],
                ['id_comercio' => 3, 'activo' => true],
                ['id_comercio' => 4, 'activo' => true],
                ['id_comercio' => 5, 'activo' => true],
            ];

            foreach ($comerciosEstado as $estado) {
                DB::table('estado_comercios_catalogo')->insertOrIgnore([
                    'id_comercio' => $estado['id_comercio'],
                    'activo' => $estado['activo'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 2. Categorías
            $categorias = [
                ['id_categoria' => 1, 'id_categoria_padre' => null, 'nombre' => 'Tecnología', 'descripcion' => 'Productos tecnológicos'],
                ['id_categoria' => 2, 'id_categoria_padre' => 1, 'nombre' => 'Accesorios de Cómputo', 'descripcion' => 'Periféricos y accesorios'],
                ['id_categoria' => 3, 'id_categoria_padre' => null, 'nombre' => 'Ropa y Calzado', 'descripcion' => 'Vestimenta y calzado'],
                ['id_categoria' => 4, 'id_categoria_padre' => 3, 'nombre' => 'Calzado Deportivo', 'descripcion' => 'Tenis y calzado deportivo'],
                ['id_categoria' => 5, 'id_categoria_padre' => null, 'nombre' => 'Alimentos y Bebidas', 'descripcion' => 'Productos alimenticios'],
                ['id_categoria' => 6, 'id_categoria_padre' => 5, 'nombre' => 'Café Gourmet', 'descripcion' => 'Café en grano y molido'],
            ];

            foreach ($categorias as $cat) {
                DB::table('categorias')->updateOrInsert(
                    ['id_categoria' => $cat['id_categoria']],
                    [
                        'id_categoria_padre' => $cat['id_categoria_padre'],
                        'nombre' => $cat['nombre'],
                        'descripcion' => $cat['descripcion'],
                        'updated_at' => now(),
                    ]
                );
            }

            // 3. Marcas
            $marcas = [
                ['id_marca' => 1, 'nombre' => 'Logitech', 'logotipo_url' => 'https://example.com/logitech.png', 'destacada' => true],
            ];

            foreach ($marcas as $m) {
                DB::table('marcas')->updateOrInsert(
                    ['id_marca' => $m['id_marca']],
                    [
                        'nombre' => $m['nombre'],
                        'logotipo_url' => $m['logotipo_url'],
                        'destacada' => $m['destacada'],
                        'updated_at' => now(),
                    ]
                );
            }

            // 4. Productos
            $productos = [
                ['id_producto' => 1, 'id_comercio' => 1, 'id_categoria' => 2, 'id_marca' => 1, 'nombre' => 'Mouse inalámbrico Logitech MQ291', 'descripcion' => 'Mouse ergonómico inalámbrico', 'video_url' => null, 'destacado' => true, 'estado_producto' => 'publicado'],
                ['id_producto' => 2, 'id_comercio' => 1, 'id_categoria' => 2, 'id_marca' => 1, 'nombre' => 'Teclado mecánico Logitech K835', 'descripcion' => 'Teclado mecánico compacto', 'video_url' => null, 'destacado' => false, 'estado_producto' => 'publicado'],
                ['id_producto' => 3, 'id_comercio' => 2, 'id_categoria' => 2, 'id_marca' => null, 'nombre' => 'Audífonos Bluetooth Over-Ear', 'descripcion' => 'Audífonos con cancelación de ruido', 'video_url' => null, 'destacado' => false, 'estado_producto' => 'publicado'],
                ['id_producto' => 4, 'id_comercio' => 3, 'id_categoria' => 2, 'id_marca' => null, 'nombre' => 'Cámara Web Full HD 1080p', 'descripcion' => 'Cámara web con micrófono integrado', 'video_url' => null, 'destacado' => true, 'estado_producto' => 'publicado'],
                ['id_producto' => 5, 'id_comercio' => 3, 'id_categoria' => 2, 'id_marca' => null, 'nombre' => 'Lente de cámara para Streaming', 'descripcion' => 'Lente gran angular para videollamadas', 'video_url' => null, 'destacado' => false, 'estado_producto' => 'publicado'],
                ['id_producto' => 6, 'id_comercio' => 4, 'id_categoria' => 4, 'id_marca' => null, 'nombre' => 'Tenis Deportivas Running Pro', 'descripcion' => 'Tenis para correr de alto rendimiento', 'video_url' => null, 'destacado' => true, 'estado_producto' => 'publicado'],
                ['id_producto' => 7, 'id_comercio' => 4, 'id_categoria' => 3, 'id_marca' => null, 'nombre' => 'Camisa Deportiva Dry-Fit', 'descripcion' => 'Camisa transpirable para ejercicio', 'video_url' => null, 'destacado' => false, 'estado_producto' => 'publicado'],
                ['id_producto' => 8, 'id_comercio' => 5, 'id_categoria' => 6, 'id_marca' => null, 'nombre' => 'Café Gourmet en Grano 500g', 'descripcion' => 'Café artesanal de altura', 'video_url' => null, 'destacado' => true, 'estado_producto' => 'publicado'],
                ['id_producto' => 9, 'id_comercio' => 5, 'id_categoria' => 6, 'id_marca' => null, 'nombre' => 'Café Gourmet Molido 340g', 'descripcion' => 'Café molido tueste medio', 'video_url' => null, 'destacado' => false, 'estado_producto' => 'publicado'],
                ['id_producto' => 10, 'id_comercio' => 5, 'id_categoria' => 5, 'id_marca' => null, 'nombre' => 'Taza Cerámica Corporativa', 'descripcion' => 'Taza térmica de cerámica', 'video_url' => null, 'destacado' => false, 'estado_producto' => 'publicado'],
                ['id_producto' => 301, 'id_comercio' => 1, 'id_categoria' => 2, 'id_marca' => 1, 'nombre' => 'Monitor LED Full HD 24 pulgadas', 'descripcion' => 'Monitor LED Full HD para trabajo y entretenimiento', 'video_url' => null, 'destacado' => true, 'estado_producto' => 'publicado'],
                ['id_producto' => 401, 'id_comercio' => 2, 'id_categoria' => 2, 'id_marca' => null, 'nombre' => 'Laptop Lenovo IdeaPad', 'descripcion' => 'Computadora portátil para productividad y trabajo', 'video_url' => null, 'destacado' => true, 'estado_producto' => 'publicado'],
            ];

            foreach ($productos as $prod) {
                DB::table('productos')->insertOrIgnore([
                    'id_producto' => $prod['id_producto'],
                    'id_comercio' => $prod['id_comercio'],
                    'id_categoria' => $prod['id_categoria'],
                    'id_marca' => $prod['id_marca'],
                    'nombre' => $prod['nombre'],
                    'descripcion' => $prod['descripcion'],
                    'video_url' => $prod['video_url'],
                    'destacado' => $prod['destacado'],
                    'estado_producto' => $prod['estado_producto'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 5. Variaciones de Producto
            // Estrategia: [id_variacion, id_producto, id_comercio, sku, precio]
            $variaciones = [
                ['id_variacion' => 1, 'id_producto' => 1, 'id_comercio' => 1, 'sku' => 'SDM-LOG-MQ291-NEG', 'precio' => 10000.00, 'tarifa_impuesto' => 13.00, 'disponible' => true],
                ['id_variacion' => 2, 'id_producto' => 2, 'id_comercio' => 1, 'sku' => 'SDM-LOG-K835-GRIS', 'precio' => 28000.00, 'tarifa_impuesto' => 13.00, 'disponible' => true],
                ['id_variacion' => 3, 'id_producto' => 3, 'id_comercio' => 2, 'sku' => 'TE-AUD-BT-BLA', 'precio' => 15000.00, 'tarifa_impuesto' => 13.00, 'disponible' => true],
                ['id_variacion' => 4, 'id_producto' => 4, 'id_comercio' => 3, 'sku' => 'ELG-CAM-FHD-NEG', 'precio' => 32000.00, 'tarifa_impuesto' => 13.00, 'disponible' => true],
                ['id_variacion' => 5, 'id_producto' => 5, 'id_comercio' => 3, 'sku' => 'ELG-CAM-LIN-M-AZU', 'precio' => 25000.00, 'tarifa_impuesto' => 13.00, 'disponible' => true],
                ['id_variacion' => 6, 'id_producto' => 6, 'id_comercio' => 4, 'sku' => 'DP-TENIS-RUN-40', 'precio' => 38900.00, 'tarifa_impuesto' => 13.00, 'disponible' => true],
                ['id_variacion' => 7, 'id_producto' => 6, 'id_comercio' => 4, 'sku' => 'DP-TENIS-RUN-42', 'precio' => 38900.00, 'tarifa_impuesto' => 13.00, 'disponible' => true],
                ['id_variacion' => 8, 'id_producto' => 7, 'id_comercio' => 4, 'sku' => 'DP-CAM-DRY-S', 'precio' => 18500.00, 'tarifa_impuesto' => 13.00, 'disponible' => true],
                ['id_variacion' => 9, 'id_producto' => 8, 'id_comercio' => 5, 'sku' => 'CV-GRA-500G-MED', 'precio' => 6500.00, 'tarifa_impuesto' => 1.00, 'disponible' => true],
                ['id_variacion' => 10, 'id_producto' => 9, 'id_comercio' => 5, 'sku' => 'CV-MOL-340G-MED', 'precio' => 4200.00, 'tarifa_impuesto' => 1.00, 'disponible' => true],
                ['id_variacion' => 11, 'id_producto' => 10, 'id_comercio' => 5, 'sku' => 'CV-TAZ-CER-BLA', 'precio' => 3333.00, 'tarifa_impuesto' => 13.00, 'disponible' => true],
                ['id_variacion' => 501, 'id_producto' => 301, 'id_comercio' => 1, 'sku' => 'SDM-MON-FHD-24', 'precio' => 9500.00, 'tarifa_impuesto' => 13.00, 'disponible' => true],
                ['id_variacion' => 502, 'id_producto' => 301, 'id_comercio' => 1, 'sku' => 'SDM-MON-FHD-24-PRO', 'precio' => 10000.00, 'tarifa_impuesto' => 13.00, 'disponible' => true],
                ['id_variacion' => 601, 'id_producto' => 401, 'id_comercio' => 2, 'sku' => 'TE-LEN-IDP-01', 'precio' => 25000.00, 'tarifa_impuesto' => 13.00, 'disponible' => true],
            ];

            foreach ($variaciones as $v) {
                DB::table('producto_variaciones')->insertOrIgnore([
                    'id_variacion' => $v['id_variacion'],
                    'id_producto' => $v['id_producto'],
                    'id_comercio' => $v['id_comercio'],
                    'sku' => $v['sku'],
                    'precio' => $v['precio'],
                    'tarifa_impuesto' => $v['tarifa_impuesto'],
                    'disponible' => $v['disponible'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 6. Atributos de Variación
            $atributos = [
                ['id_variacion' => 1, 'tipo_atributo' => 'Color', 'valor' => 'Negro'],
                ['id_variacion' => 2, 'tipo_atributo' => 'Color', 'valor' => 'Gris Oscuro'],
                ['id_variacion' => 3, 'tipo_atributo' => 'Color', 'valor' => 'Blanco'],
                ['id_variacion' => 4, 'tipo_atributo' => 'Resolución', 'valor' => '1080p'],
                ['id_variacion' => 5, 'tipo_atributo' => 'Color', 'valor' => 'Azul'],
                ['id_variacion' => 6, 'tipo_atributo' => 'Talla', 'valor' => '40'],
                ['id_variacion' => 7, 'tipo_atributo' => 'Talla', 'valor' => '42'],
                ['id_variacion' => 8, 'tipo_atributo' => 'Talla', 'valor' => 'S'],
                ['id_variacion' => 9, 'tipo_atributo' => 'Tueste', 'valor' => 'Medio'],
                ['id_variacion' => 10, 'tipo_atributo' => 'Presentación', 'valor' => 'Molido'],
                ['id_variacion' => 11, 'tipo_atributo' => 'Color', 'valor' => 'Blanco'],
            ];

            foreach ($atributos as $at) {
                DB::table('atributos_variacion')->updateOrInsert(
                    [
                        'id_variacion' => $at['id_variacion'],
                        'tipo_atributo' => $at['tipo_atributo'],
                    ],
                    [
                        'valor' => $at['valor'],
                        'updated_at' => now(),
                    ]
                );
            }

            // 7. Imágenes de Producto
            $imagenes = [
                ['id_producto' => 1, 'id_variacion' => 1, 'imagen_url' => 'https://example.com/mouse-negro.jpg', 'formato' => 'jpg', 'tamano_bytes' => 150000, 'orden' => 1],
                ['id_producto' => 2, 'id_variacion' => 2, 'imagen_url' => 'https://example.com/teclado-k835.jpg', 'formato' => 'jpg', 'tamano_bytes' => 220000, 'orden' => 1],
                ['id_producto' => 3, 'id_variacion' => 3, 'imagen_url' => 'https://example.com/audifonos-blancos.jpg', 'formato' => 'jpg', 'tamano_bytes' => 180000, 'orden' => 1],
                ['id_producto' => 4, 'id_variacion' => 4, 'imagen_url' => 'https://example.com/webcam-1080p.jpg', 'formato' => 'jpg', 'tamano_bytes' => 210000, 'orden' => 1],
                ['id_producto' => 5, 'id_variacion' => 5, 'imagen_url' => 'https://example.com/lente-azul.jpg', 'formato' => 'jpg', 'tamano_bytes' => 195000, 'orden' => 1],
                ['id_producto' => 6, 'id_variacion' => 6, 'imagen_url' => 'https://example.com/tenis-running-40.jpg', 'formato' => 'jpg', 'tamano_bytes' => 310000, 'orden' => 1],
                ['id_producto' => 7, 'id_variacion' => 8, 'imagen_url' => 'https://example.com/camisa-dryfit-s.jpg', 'formato' => 'jpg', 'tamano_bytes' => 140000, 'orden' => 1],
                ['id_producto' => 8, 'id_variacion' => 9, 'imagen_url' => 'https://example.com/cafe-grano-500g.jpg', 'formato' => 'jpg', 'tamano_bytes' => 250000, 'orden' => 1],
                ['id_producto' => 9, 'id_variacion' => 10, 'imagen_url' => 'https://example.com/cafe-molido-340g.jpg', 'formato' => 'jpg', 'tamano_bytes' => 230000, 'orden' => 1],
                ['id_producto' => 10, 'id_variacion' => 11, 'imagen_url' => 'https://example.com/taza-blanca.jpg', 'formato' => 'jpg', 'tamano_bytes' => 120000, 'orden' => 1],
            ];

            foreach ($imagenes as $img) {
                DB::table('imagenes_producto')->updateOrInsert(
                    [
                        'id_producto' => $img['id_producto'],
                        'orden' => $img['orden'],
                    ],
                    [
                        'id_variacion' => $img['id_variacion'],
                        'imagen_url' => $img['imagen_url'],
                        'formato' => $img['formato'],
                        'tamano_bytes' => $img['tamano_bytes'],
                        'updated_at' => now(),
                    ]
                );
            }
        });

        // 8. Sincronizar las secuencias de PostgreSQL
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
