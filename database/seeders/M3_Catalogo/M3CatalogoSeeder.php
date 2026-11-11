<?php

declare(strict_types=1);

namespace Database\Seeders\M3_Catalogo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catalogo de los escenarios de demostracion (seccion 2.2 del colectivo del PC4):
 * 10 productos y 11 variaciones con id fijos, para que M4 (existencias) y M5 (pedidos)
 * los referencien en sus propios seeders. Los comercios 1 a 5 son los de ComerciosDemoSeeder (M2).
 *
 * Es idempotente (upsert por llave primaria). Ejecutar solo con --class, nunca db:seed global:
 *     php artisan db:seed --class="Database\Seeders\M3_Catalogo\M3CatalogoSeeder"
 */
class M3CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $t = now();

        DB::transaction(function () use ($t): void {
            $this->upsert('categorias', 'id_categoria', [
                [1, 'Tecnología', 'Dispositivos y accesorios'],
                [2, 'Hogar y oficina', 'Mobiliario y equipo'],
                [3, 'Ropa', 'Indumentaria general'],
                [4, 'Deportes', 'Artículos deportivos'],
                [5, 'Alimentos y bebidas', 'Consumibles'],
                [6, 'Cocina y mesa', 'Utensilios de cocina'],
            ], ['id_categoria', 'nombre', 'descripcion'], $t, ['id_categoria_padre' => null]);

            $this->upsert('marcas', 'id_marca', [
                [1, 'Logitech', 'https://example.com/logitech.png', true],
            ], ['id_marca', 'nombre', 'logotipo_url', 'destacada'], $t);

            // [id_producto, id_comercio, id_categoria, id_marca, nombre, destacado]
            $this->upsert('productos', 'id_producto', [
                [1, 1, 1, 1, 'Mouse inalámbrico Logitech MQ291', true],
                [2, 1, 1, null, 'Audífonos Bluetooth', false],
                [3, 2, 2, null, 'Lámpara LED de escritorio', false],
                [4, 2, 2, null, 'Organizador de escritorio', false],
                [5, 3, 3, null, 'Camisa de lino manga larga', false],
                [6, 3, 3, null, 'Pantalón chino', false],
                [7, 4, 4, null, 'Camiseta deportiva', false],
                [8, 4, 4, null, 'Botella térmica 750 ml', false],
                [9, 5, 5, null, 'Café molido 340 g', false],
                [10, 5, 6, null, 'Taza de cerámica', false],
            ], ['id_producto', 'id_comercio', 'id_categoria', 'id_marca', 'nombre', 'destacado'], $t, [
                'descripcion' => null,
                'video_url' => null,
                'estado_producto' => 'publicado',
            ]);

            // [id_variacion, id_producto, id_comercio, sku, precio, tipo_descuento, valor_descuento]
                        // [id_variacion, id_producto, id_comercio, sku, precio]  (el descuento lo aplica la promocion de M1)
            $this->upsert('producto_variaciones', 'id_variacion', [
                [1, 1, 1, 'SDM-LOG-MQ291-NEG', 10000],
                [2, 2, 1, 'SDM-AUD-BT-NEG', 15000],
                [3, 3, 2, 'TML-LAMP-LED-BLA', 4500],
                [4, 4, 2, 'TML-ORG-ESC-GRI', 3500],
                [5, 5, 3, 'ELG-CAM-LIN-M-AZU', 25000],
                [6, 6, 3, 'ELG-PAN-CHI-32-BEI', 30000],
                [7, 7, 4, 'DP-CAM-DEP-S', 8990],
                [8, 7, 4, 'DP-CAM-DEP-XL', 9490],
                [9, 8, 4, 'DP-BOT-TER-NEG', 6750],
                [10, 9, 5, 'CV-CAF-340-MED', 5425],
                [11, 10, 5, 'CV-TAZ-CER-BLA', 3333],
            ], ['id_variacion', 'id_producto', 'id_comercio', 'sku', 'precio'], $t, [
                'tarifa_impuesto' => 13,
                'codigo_barras' => null,
                'codigo_fabricante' => null,
                'disponible' => true,
            ]);

            // [id_atributo, id_variacion, tipo_atributo, valor]
            $this->upsert('atributos_variacion', 'id_atributo', [
                [1, 1, 'Color', 'Negro'],
                [2, 2, 'Color', 'Negro'],
                [3, 3, 'Color', 'Blanco'],
                [4, 4, 'Color', 'Gris'],
                [5, 5, 'Talla', 'M'],
                [6, 5, 'Color', 'Azul'],
                [7, 6, 'Talla', '32'],
                [8, 6, 'Color', 'Beige'],
                [9, 7, 'Talla', 'S'],
                [10, 8, 'Talla', 'XL'],
                [11, 9, 'Color', 'Negra'],
                [12, 10, 'Tueste', 'Medio'],
                [13, 11, 'Color', 'Blanca'],
            ], ['id_atributo', 'id_variacion', 'tipo_atributo', 'valor'], $t);

            // Una imagen por producto (la regla de 1 a 8 imagenes aplica al publicar).
            $imagenes = [];
            foreach (range(1, 10) as $n) {
                $imagenes[] = [$n, $n, "https://example.com/producto-{$n}.jpg", 'jpg', 120000, 1];
            }
            $this->upsert('imagenes_producto', 'id_imagen', $imagenes,
                ['id_imagen', 'id_producto', 'imagen_url', 'formato', 'tamano_bytes', 'orden'], $t, ['id_variacion' => null]);

            // Proyeccion local del estado del comercio (D2): los cinco comercios de la demo, activos.
            $estados = [];
            foreach (range(1, 5) as $idComercio) {
                $estados[] = [
                    'id_comercio' => $idComercio,
                    'activo' => true,
                    'fecha_ultimo_evento' => null,
                    'id_ultimo_evento' => null,
                    'created_at' => $t,
                    'updated_at' => $t,
                ];
            }
            DB::table('estado_comercios_catalogo')->upsert($estados, ['id_comercio'], ['activo', 'updated_at']);
        });

        $this->reajustarSecuencias();
    }

    /**
     * @param  list<list<mixed>>  $filas  valores en el orden de $columnas
     * @param  list<string>  $columnas
     * @param  array<string, mixed>  $fijos  columnas con el mismo valor en todas las filas
     */
    private function upsert(string $tabla, string $llave, array $filas, array $columnas, $t, array $fijos = []): void
    {
        $registros = array_map(
            fn (array $fila): array => array_combine($columnas, $fila) + $fijos + ['created_at' => $t, 'updated_at' => $t],
            $filas
        );

        $actualizar = array_values(array_diff(array_keys($registros[0]), [$llave, 'created_at']));

        DB::table($tabla)->upsert($registros, [$llave], $actualizar);
    }

    /** Al insertar con id explicito la secuencia no avanza: sin esto el siguiente INSERT choca con el id 1. */
    private function reajustarSecuencias(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([
            'categorias' => 'id_categoria',
            'marcas' => 'id_marca',
            'productos' => 'id_producto',
            'producto_variaciones' => 'id_variacion',
            'atributos_variacion' => 'id_atributo',
            'imagenes_producto' => 'id_imagen',
        ] as $tabla => $llave) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$tabla}','{$llave}'), (SELECT MAX({$llave}) FROM {$tabla}))");
        }
    }
}