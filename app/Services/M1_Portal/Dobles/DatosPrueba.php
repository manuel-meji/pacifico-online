<?php

namespace App\Services\M1_Portal\Dobles;

/**
 * Conjunto de datos ficticios que comparten los dobles de M2, M3, M4 y M5.
 *
 * Los identificadores son los mismos en todos los dobles: un producto de M3
 * apunta a un comercio de M2, y las existencias de M4 apuntan a las
 * variaciones y productos de M3. Cada caso de error que depende de un dato
 * (comercio suspendido, variación agotada, etc.) tiene aquí su constante.
 */
final class DatosPrueba
{
    public const MONEDA = 'CRC';

    // Comercios (M2)
    public const COMERCIO_LA_CEIBA = 34;

    public const COMERCIO_CAFE_NICOYANO = 35;

    public const COMERCIO_ARTESANIAS_GUAITIL = 36;

    public const COMERCIO_SUSPENDIDO = 37;

    public const COMERCIO_INEXISTENTE = 99;

    // Categorías (M3)
    public const CATEGORIA_ROPA = 10;

    public const CATEGORIA_CAMISAS = 11;

    public const CATEGORIA_SOMBREROS = 12;

    public const CATEGORIA_ALIMENTOS = 20;

    public const CATEGORIA_CAFE = 21;

    public const CATEGORIA_ARTESANIA = 30;

    public const CATEGORIA_CERAMICA = 31;

    public const CATEGORIA_SOLO_SUSPENDIDO = 40;

    public const CATEGORIA_VACIA = 50;

    // Marcas (M3)
    public const MARCA_CEIBA_WEAR = 71;

    public const MARCA_CAFE_NICOYANO = 72;

    // Productos (M3)
    public const PRODUCTO_CAMISA_LINO = 1520;

    public const PRODUCTO_SOMBRERO_AGOTADO = 1521;

    public const PRODUCTO_CAFE_MOLIDO = 1522;

    public const PRODUCTO_CAFE_TOSTADO = 1523;

    public const PRODUCTO_JARRON = 1524;

    public const PRODUCTO_TAZA_SIN_EXISTENCIA = 1525;

    public const PRODUCTO_DE_COMERCIO_SUSPENDIDO = 1526;

    public const PRODUCTO_INEXISTENTE = 9999;

    // Variaciones (M3) con su caso en M4
    public const VARIACION_CAMISA_S = 15201;

    public const VARIACION_CAMISA_M_AGOTADA = 15202;

    public const VARIACION_CAMISA_L_BAJO_STOCK = 15203;

    public const VARIACION_SOMBRERO_AGOTADA = 15211;

    public const VARIACION_CAFE_MOLIDO = 15221;

    public const VARIACION_CAFE_GRANO = 15222;

    public const VARIACION_CAFE_TOSTADO = 15231;

    public const VARIACION_JARRON_NO_VENDIBLE = 15241;

    public const VARIACION_JARRON_PEQUENO = 15242;

    public const VARIACION_TAZA_SIN_EXISTENCIA = 15251;

    public const VARIACION_DE_COMERCIO_SUSPENDIDO = 15261;

    public const VARIACION_INEXISTENTE = 88888;

    // Ubicaciones (id_sede de M2 = id_ubicacion de M4)
    public const UBICACION_LA_CEIBA_TIENDA = 340;

    public const UBICACION_LA_CEIBA_BODEGA = 341;

    public const UBICACION_CAFE_NICOYANO = 350;

    public const UBICACION_GUAITIL = 360;

    public const UBICACION_POCHOTE = 370;

    // Clientes y carritos (M5)
    public const CLIENTE_CON_CARRITO = 1;

    public const CLIENTE_CARRITO_VACIO = 2;

    public const CARRITO_CLIENTE_CON_CARRITO = 501;

    public const CARRITO_CLIENTE_VACIO = 502;

    /**
     * Comercios de M2 con la estructura de ComercioPublico (1.2.1). Los campos
     * destacado e inicio_publicidad son internos del doble.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function comercios(): array
    {
        return [
            self::COMERCIO_LA_CEIBA => self::comercio(
                self::COMERCIO_LA_CEIBA, 'Tienda La Ceiba', 'Ropa fresca de lino y accesorios hechos en Guanacaste.',
                'Activo', true, '2026-10-05',
            ),
            self::COMERCIO_CAFE_NICOYANO => self::comercio(
                self::COMERCIO_CAFE_NICOYANO, 'Café Nicoyano', 'Café de altura tostado en Nicoya.',
                'Activo', false, null,
            ),
            self::COMERCIO_ARTESANIAS_GUAITIL => self::comercio(
                self::COMERCIO_ARTESANIAS_GUAITIL, 'Artesanías Guaitil', 'Cerámica chorotega elaborada a mano.',
                'Activo', true, '2026-10-10',
            ),
            self::COMERCIO_SUSPENDIDO => self::comercio(
                self::COMERCIO_SUSPENDIDO, 'Ferretería El Pochote', 'Herramientas y materiales de construcción.',
                'Suspendido', false, null,
            ),
        ];
    }

    /**
     * Categorías de M3 como lista plana con id_categoria_padre.
     *
     * @return list<array{id_categoria: int, nombre: string, id_categoria_padre: int|null, icono_url: string|null}>
     */
    public static function categorias(): array
    {
        return [
            self::categoria(self::CATEGORIA_ROPA, 'Ropa', null),
            self::categoria(self::CATEGORIA_CAMISAS, 'Camisas', self::CATEGORIA_ROPA),
            self::categoria(self::CATEGORIA_SOMBREROS, 'Sombreros', self::CATEGORIA_ROPA),
            self::categoria(self::CATEGORIA_ALIMENTOS, 'Alimentos', null),
            self::categoria(self::CATEGORIA_CAFE, 'Café', self::CATEGORIA_ALIMENTOS),
            self::categoria(self::CATEGORIA_ARTESANIA, 'Artesanía', null),
            self::categoria(self::CATEGORIA_CERAMICA, 'Cerámica', self::CATEGORIA_ARTESANIA),
            self::categoria(self::CATEGORIA_SOLO_SUSPENDIDO, 'Ferretería', null),
            self::categoria(self::CATEGORIA_VACIA, 'Juguetes', null),
        ];
    }

    /**
     * @return list<array{id_marca: int, nombre: string}>
     */
    public static function marcas(): array
    {
        return [
            ['id_marca' => self::MARCA_CEIBA_WEAR, 'nombre' => 'Ceiba Wear'],
            ['id_marca' => self::MARCA_CAFE_NICOYANO, 'nombre' => 'Café Nicoyano'],
        ];
    }

    /**
     * Productos de M3 con sus variaciones. Los campos relacionados y
     * publicado_en son internos del doble.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function productos(): array
    {
        return [
            self::PRODUCTO_CAMISA_LINO => self::producto(
                self::PRODUCTO_CAMISA_LINO, self::COMERCIO_LA_CEIBA, self::CATEGORIA_CAMISAS, self::MARCA_CEIBA_WEAR,
                'Camisa de lino', 'Camisa de lino 100 %, ideal para el calor de la bajura.', '2026-09-01',
                [self::PRODUCTO_SOMBRERO_AGOTADO, self::PRODUCTO_JARRON],
                [
                    self::variacion(self::VARIACION_CAMISA_S, 'CEI-CAM-S-BL', 18500.00, true, ['Talla' => 'S', 'Color' => 'Blanco']),
                    self::variacion(self::VARIACION_CAMISA_M_AGOTADA, 'CEI-CAM-M-BL', 18500.00, true, ['Talla' => 'M', 'Color' => 'Blanco']),
                    self::variacion(self::VARIACION_CAMISA_L_BAJO_STOCK, 'CEI-CAM-L-AZ', 21000.00, true, ['Talla' => 'L', 'Color' => 'Azul']),
                ],
            ),
            self::PRODUCTO_SOMBRERO_AGOTADO => self::producto(
                self::PRODUCTO_SOMBRERO_AGOTADO, self::COMERCIO_LA_CEIBA, self::CATEGORIA_SOMBREROS, self::MARCA_CEIBA_WEAR,
                'Sombrero de palma', 'Sombrero tejido a mano con palma natural.', '2026-09-05',
                [self::PRODUCTO_CAMISA_LINO],
                [
                    self::variacion(self::VARIACION_SOMBRERO_AGOTADA, 'CEI-SOM-UNI', 12000.00, true, ['Talla' => 'Única']),
                ],
            ),
            self::PRODUCTO_CAFE_MOLIDO => self::producto(
                self::PRODUCTO_CAFE_MOLIDO, self::COMERCIO_CAFE_NICOYANO, self::CATEGORIA_CAFE, self::MARCA_CAFE_NICOYANO,
                'Café molido 500 g', 'Café de altura de tueste medio.', '2026-09-10',
                [self::PRODUCTO_CAFE_TOSTADO],
                [
                    self::variacion(self::VARIACION_CAFE_MOLIDO, 'NIC-500-MOL', 6500.00, true, ['Presentación' => 'Molido']),
                    self::variacion(self::VARIACION_CAFE_GRANO, 'NIC-500-GRA', 6800.00, true, ['Presentación' => 'Grano']),
                ],
            ),
            self::PRODUCTO_CAFE_TOSTADO => self::producto(
                self::PRODUCTO_CAFE_TOSTADO, self::COMERCIO_CAFE_NICOYANO, self::CATEGORIA_CAFE, self::MARCA_CAFE_NICOYANO,
                'Café tostado oscuro 1 kg', 'Café de tueste oscuro para espresso.', '2026-09-20',
                [self::PRODUCTO_CAFE_MOLIDO],
                [
                    self::variacion(self::VARIACION_CAFE_TOSTADO, 'NIC-1K-OSC', 11500.00, true, ['Presentación' => 'Grano']),
                ],
            ),
            self::PRODUCTO_JARRON => self::producto(
                self::PRODUCTO_JARRON, self::COMERCIO_ARTESANIAS_GUAITIL, self::CATEGORIA_CERAMICA, null,
                'Jarrón de barro chorotega', 'Jarrón pintado con motivos chorotegas.', '2026-10-01',
                [self::PRODUCTO_TAZA_SIN_EXISTENCIA, self::PRODUCTO_DE_COMERCIO_SUSPENDIDO],
                [
                    self::variacion(self::VARIACION_JARRON_NO_VENDIBLE, 'GUA-JAR-GDE', 25000.00, false, ['Tamaño' => 'Grande']),
                    self::variacion(self::VARIACION_JARRON_PEQUENO, 'GUA-JAR-PEQ', 14000.00, true, ['Tamaño' => 'Pequeño']),
                ],
            ),
            self::PRODUCTO_TAZA_SIN_EXISTENCIA => self::producto(
                self::PRODUCTO_TAZA_SIN_EXISTENCIA, self::COMERCIO_ARTESANIAS_GUAITIL, self::CATEGORIA_CERAMICA, null,
                'Taza de cerámica', 'Taza de barro para café.', '2026-10-03',
                [self::PRODUCTO_JARRON],
                [
                    self::variacion(self::VARIACION_TAZA_SIN_EXISTENCIA, 'GUA-TAZ-UNI', 7000.00, true, []),
                ],
            ),
            self::PRODUCTO_DE_COMERCIO_SUSPENDIDO => self::producto(
                self::PRODUCTO_DE_COMERCIO_SUSPENDIDO, self::COMERCIO_SUSPENDIDO, self::CATEGORIA_SOLO_SUSPENDIDO, null,
                'Martillo de uña', 'Martillo de acero con mango de madera.', '2026-08-15',
                [],
                [
                    self::variacion(self::VARIACION_DE_COMERCIO_SUSPENDIDO, 'POC-MAR-16', 9000.00, true, ['Peso' => '16 oz']),
                ],
            ),
        ];
    }

    /**
     * Existencias de M4 por comercio, variación y ubicación. La taza
     * (VARIACION_TAZA_SIN_EXISTENCIA) no tiene registro a propósito.
     *
     * @return list<array{id_comercio: int, id_producto: int, id_variacion: int, id_ubicacion: int, cantidad_disponible: int, stock_minimo: int}>
     */
    public static function existencias(): array
    {
        return [
            self::existencia(self::COMERCIO_LA_CEIBA, self::PRODUCTO_CAMISA_LINO, self::VARIACION_CAMISA_S, self::UBICACION_LA_CEIBA_TIENDA, 3, 1),
            self::existencia(self::COMERCIO_LA_CEIBA, self::PRODUCTO_CAMISA_LINO, self::VARIACION_CAMISA_S, self::UBICACION_LA_CEIBA_BODEGA, 2, 1),
            self::existencia(self::COMERCIO_LA_CEIBA, self::PRODUCTO_CAMISA_LINO, self::VARIACION_CAMISA_M_AGOTADA, self::UBICACION_LA_CEIBA_TIENDA, 0, 1),
            self::existencia(self::COMERCIO_LA_CEIBA, self::PRODUCTO_CAMISA_LINO, self::VARIACION_CAMISA_L_BAJO_STOCK, self::UBICACION_LA_CEIBA_TIENDA, 2, 3),
            self::existencia(self::COMERCIO_LA_CEIBA, self::PRODUCTO_SOMBRERO_AGOTADO, self::VARIACION_SOMBRERO_AGOTADA, self::UBICACION_LA_CEIBA_TIENDA, 0, 2),
            self::existencia(self::COMERCIO_CAFE_NICOYANO, self::PRODUCTO_CAFE_MOLIDO, self::VARIACION_CAFE_MOLIDO, self::UBICACION_CAFE_NICOYANO, 40, 10),
            self::existencia(self::COMERCIO_CAFE_NICOYANO, self::PRODUCTO_CAFE_MOLIDO, self::VARIACION_CAFE_GRANO, self::UBICACION_CAFE_NICOYANO, 30, 10),
            self::existencia(self::COMERCIO_CAFE_NICOYANO, self::PRODUCTO_CAFE_TOSTADO, self::VARIACION_CAFE_TOSTADO, self::UBICACION_CAFE_NICOYANO, 8, 5),
            self::existencia(self::COMERCIO_ARTESANIAS_GUAITIL, self::PRODUCTO_JARRON, self::VARIACION_JARRON_NO_VENDIBLE, self::UBICACION_GUAITIL, 3, 1),
            self::existencia(self::COMERCIO_ARTESANIAS_GUAITIL, self::PRODUCTO_JARRON, self::VARIACION_JARRON_PEQUENO, self::UBICACION_GUAITIL, 6, 2),
            self::existencia(self::COMERCIO_SUSPENDIDO, self::PRODUCTO_DE_COMERCIO_SUSPENDIDO, self::VARIACION_DE_COMERCIO_SUSPENDIDO, self::UBICACION_POCHOTE, 10, 2),
        ];
    }

    /**
     * Carritos iniciales de M5, con la estructura de Carrito (1.1.0) sin
     * monto_estimado, que el doble calcula.
     *
     * @return array<int, array{id_carrito: int, id_cliente: int, created_at: string, updated_at: string, items: list<array<string, mixed>>}>
     */
    public static function carritos(): array
    {
        return [
            self::CLIENTE_CON_CARRITO => [
                'id_carrito' => self::CARRITO_CLIENTE_CON_CARRITO,
                'id_cliente' => self::CLIENTE_CON_CARRITO,
                'created_at' => '2026-10-15T09:00:00-06:00',
                'updated_at' => '2026-10-15T09:00:00-06:00',
                'items' => [
                    [
                        'id_item_carrito' => 9001,
                        'id_carrito' => self::CARRITO_CLIENTE_CON_CARRITO,
                        'id_variacion' => self::VARIACION_CAFE_GRANO,
                        'cantidad' => 2,
                        'id_reserva' => null,
                        'created_at' => '2026-10-15T09:00:00-06:00',
                        'updated_at' => '2026-10-15T09:00:00-06:00',
                    ],
                ],
            ],
            self::CLIENTE_CARRITO_VACIO => [
                'id_carrito' => self::CARRITO_CLIENTE_VACIO,
                'id_cliente' => self::CLIENTE_CARRITO_VACIO,
                'created_at' => '2026-10-15T09:00:00-06:00',
                'updated_at' => '2026-10-15T09:00:00-06:00',
                'items' => [],
            ],
        ];
    }

    /**
     * Busca una variación en el catálogo y la devuelve junto con su producto.
     *
     * @return array{producto: array<string, mixed>, variacion: array<string, mixed>}|null
     */
    public static function buscarVariacion(int $idVariacion): ?array
    {
        foreach (self::productos() as $producto) {
            foreach ($producto['variaciones'] as $variacion) {
                if ($variacion['id_variacion'] === $idVariacion) {
                    return ['producto' => $producto, 'variacion' => $variacion];
                }
            }
        }

        return null;
    }

    /**
     * Indica si un comercio está en estado Activo (comercio habilitado).
     */
    public static function comercioActivo(int $idComercio): bool
    {
        return (self::comercios()[$idComercio]['estado'] ?? null) === 'Activo';
    }

    /**
     * @return array<string, mixed>
     */
    private static function comercio(int $id, string $nombre, string $descripcion, string $estado, bool $destacado, ?string $inicioPublicidad): array
    {
        $base = "https://cdn.pacificos.example/comercios/{$id}";

        return [
            'id_comercio' => $id,
            'nombre_comercial' => $nombre,
            'descripcion' => $descripcion,
            'logo_url' => "{$base}/logo.png",
            'banners' => [
                ['id_banner' => $id * 10 + 1, 'imagen_url' => "{$base}/banner-1.jpg", 'texto_alternativo' => "Banner de {$nombre}"],
            ],
            'galeria' => [
                ['id_imagen' => $id * 10 + 1, 'imagen_url' => "{$base}/galeria-1.jpg", 'texto_alternativo' => "Galería de {$nombre}"],
            ],
            'horarios' => self::horarios($id),
            'contacto' => [
                'telefono' => '2666-00'.$id,
                'correo' => "contacto{$id}@pacificos.example",
                'direccion' => 'Liberia, Guanacaste',
            ],
            'redes_sociales' => [
                ['plataforma' => 'Facebook', 'url' => "https://facebook.example/comercio{$id}"],
            ],
            'politicas' => [
                ['tipo' => 'devolucion', 'descripcion' => 'Se aceptan devoluciones dentro de los 8 días naturales.'],
            ],
            'estado' => $estado,
            'destacado' => $destacado,
            'inicio_publicidad' => $inicioPublicidad,
        ];
    }

    /**
     * Lunes a sábado de 08:00 a 18:00; domingo cerrado.
     *
     * @return list<array{id_horario: int, dia_semana: int, hora_apertura: string|null, hora_cierre: string|null, cerrado: bool}>
     */
    private static function horarios(int $idComercio): array
    {
        $horarios = [];

        foreach (range(1, 7) as $dia) {
            $cerrado = $dia === 7;
            $horarios[] = [
                'id_horario' => $idComercio * 10 + $dia,
                'dia_semana' => $dia,
                'hora_apertura' => $cerrado ? null : '08:00',
                'hora_cierre' => $cerrado ? null : '18:00',
                'cerrado' => $cerrado,
            ];
        }

        return $horarios;
    }

    /**
     * @return array{id_categoria: int, nombre: string, id_categoria_padre: int|null, icono_url: string|null}
     */
    private static function categoria(int $id, string $nombre, ?int $idPadre): array
    {
        return [
            'id_categoria' => $id,
            'nombre' => $nombre,
            'id_categoria_padre' => $idPadre,
            'icono_url' => $idPadre === null ? "https://cdn.pacificos.example/categorias/{$id}.svg" : null,
        ];
    }

    /**
     * @param  list<int>  $relacionados
     * @param  list<array<string, mixed>>  $variaciones
     * @return array<string, mixed>
     */
    private static function producto(
        int $id,
        int $idComercio,
        int $idCategoria,
        ?int $idMarca,
        string $nombre,
        string $descripcion,
        string $publicadoEn,
        array $relacionados,
        array $variaciones,
    ): array {
        return [
            'id_producto' => $id,
            'id_comercio' => $idComercio,
            'id_categoria' => $idCategoria,
            'id_marca' => $idMarca,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'video_url' => null,
            'moneda' => self::MONEDA,
            'imagenes' => [
                "https://cdn.pacificos.example/p/{$id}.jpg",
                "https://cdn.pacificos.example/p/{$id}-2.jpg",
            ],
            'variaciones' => $variaciones,
            'relacionados' => $relacionados,
            'publicado_en' => $publicadoEn,
        ];
    }

    /**
     * @param  array<string, string>  $atributos
     * @return array{id_variacion: int, sku: string, precio: float, disponible: bool, atributos: list<array{tipo_atributo: string, valor: string}>}
     */
    private static function variacion(int $id, string $sku, float $precio, bool $disponible, array $atributos): array
    {
        $lista = [];

        foreach ($atributos as $tipo => $valor) {
            $lista[] = ['tipo_atributo' => $tipo, 'valor' => $valor];
        }

        return [
            'id_variacion' => $id,
            'sku' => $sku,
            'precio' => $precio,
            'disponible' => $disponible,
            'atributos' => $lista,
        ];
    }

    /**
     * @return array{id_comercio: int, id_producto: int, id_variacion: int, id_ubicacion: int, cantidad_disponible: int, stock_minimo: int}
     */
    private static function existencia(int $idComercio, int $idProducto, int $idVariacion, int $idUbicacion, int $cantidad, int $stockMinimo): array
    {
        return [
            'id_comercio' => $idComercio,
            'id_producto' => $idProducto,
            'id_variacion' => $idVariacion,
            'id_ubicacion' => $idUbicacion,
            'cantidad_disponible' => $cantidad,
            'stock_minimo' => $stockMinimo,
        ];
    }
}
