<?php

namespace Tests\Feature\M3_Catalogo\Api;

use App\Enums\Nucleo\RolUsuario;
use App\Events\M3_Catalogo\ProductoPublicado;
use App\Models\Nucleo\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\M3_Catalogo\EsquemaCatalogoPrueba;
use Tests\TestCase;

/**
 * API del contrato M3 v1.3.0 en /api/m3/v1 (routes/modules/m3_catalogo_api.php):
 * lecturas públicas, escrituras con sesión del Encargado del comercio y el
 * recorrido completo de alta que usa la pantalla de registro de producto.
 */
class M3_ApiCatalogoTest extends TestCase
{
    use EsquemaCatalogoPrueba;

    private const BASE = '/api/m3/v1';

    private const COMERCIO = 1;

    private const OTRO_COMERCIO = 34;

    protected function setUp(): void
    {
        parent::setUp();

        config(['integraciones.m3.m2.modo' => 'doble']);
        $this->crearEsquemaCatalogo();
        $this->crearEstadoDeComercios();
        Event::fake([ProductoPublicado::class]);
    }

    // ---------------------------------------------------------------- lecturas públicas

    public function test_las_lecturas_responden_sin_sesion_en_el_prefijo_del_contrato(): void
    {
        DB::table('categorias')->insert(['nombre' => 'Ropa', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marcas')->insert(['nombre' => 'Pacific Sport', 'created_at' => now(), 'updated_at' => now()]);

        $this->getJson(self::BASE.'/categorias')->assertOk()->assertJsonPath('0.nombre', 'Ropa');
        $this->getJson(self::BASE.'/marcas')->assertOk()->assertJsonPath('0.nombre', 'Pacific Sport');
        $this->getJson(self::BASE.'/productos')
            ->assertOk()
            ->assertJsonStructure(['pagina', 'por_pagina', 'total_resultados', 'productos']);
        $this->getJson(self::BASE.'/productos/1001')->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
        $this->getJson(self::BASE.'/variaciones/1001')->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
        $this->getJson(self::BASE.'/productos/1001/relacionados')->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
    }

    // ---------------------------------------------------------------- sesión y rol

    public function test_las_escrituras_sin_sesion_responden_401(): void
    {
        foreach ($this->escrituras() as [$metodo, $ruta]) {
            $this->json($metodo, self::BASE.$ruta, [])
                ->assertStatus(401)
                ->assertJsonStructure(['error' => ['codigo', 'mensaje']]);
        }
    }

    public function test_un_cliente_no_puede_escribir_en_el_catalogo(): void
    {
        $this->como($this->cuenta(RolUsuario::Cliente));

        foreach ($this->escrituras() as [$metodo, $ruta]) {
            $this->json($metodo, self::BASE.$ruta, [])
                ->assertStatus(403)
                ->assertJsonStructure(['error' => ['codigo', 'mensaje']]);
        }
    }

    // ---------------------------------------------------------------- alta completa

    public function test_el_encargado_registra_un_producto_y_al_publicarlo_queda_visible(): void
    {
        $this->comercioActivoEnCatalogo(self::COMERCIO);
        $this->comoEncargado(self::COMERCIO);

        $idCategoria = $this->postJson(self::BASE.'/categorias', ['nombre' => 'Ropa'])
            ->assertCreated()
            ->json('id_categoria');
        $idMarca = $this->postJson(self::BASE.'/marcas', ['nombre' => 'Pacific Sport'])
            ->assertCreated()
            ->json('id_marca');

        $producto = $this->postJson(self::BASE.'/productos', [
            'id_categoria' => $idCategoria,
            'id_marca' => $idMarca,
            'nombre' => 'Camiseta deportiva',
            'descripcion' => 'Camiseta ligera.',
        ])->assertCreated()
            ->assertJsonPath('estado_producto', 'borrador')
            ->assertJsonPath('id_comercio', self::COMERCIO);
        $idProducto = $producto->json('id_producto');

        // En borrador no aparece en las consultas públicas.
        $this->getJson(self::BASE."/productos/{$idProducto}")->assertNotFound();

        $this->postJson(self::BASE."/productos/{$idProducto}/variaciones", [
            'sku' => 'CAM-AZU-M',
            'precio' => 8500,
            'tarifa_impuesto' => 13,
            'atributos' => [
                ['tipo_atributo' => 'Color', 'valor' => 'Azul'],
                ['tipo_atributo' => 'Talla', 'valor' => 'M'],
            ],
        ])->assertCreated()->assertJsonPath('sku', 'CAM-AZU-M');

        $this->postJson(self::BASE."/productos/{$idProducto}/imagenes", [
            'imagen_url' => 'https://example.com/camiseta.jpg',
        ])->assertCreated()->assertJsonPath('orden', 1);

        $this->patchJson(self::BASE."/productos/{$idProducto}", ['estado_producto' => 'publicado'])
            ->assertOk()
            ->assertJsonPath('estado_producto', 'publicado');
        Event::assertDispatched(ProductoPublicado::class);

        $this->getJson(self::BASE."/productos/{$idProducto}")
            ->assertOk()
            ->assertJsonPath('nombre', 'Camiseta deportiva')
            ->assertJsonCount(1, 'variaciones')
            ->assertJsonCount(1, 'imagenes')
            ->assertJsonPath('variaciones.0.atributos.0.tipo_atributo', 'Color');
        $this->getJson(self::BASE.'/productos?q=camiseta')
            ->assertOk()
            ->assertJsonPath('total_resultados', 1)
            ->assertJsonPath('productos.0.precio_desde', 8500);
    }

    public function test_no_se_publica_sin_variacion_ni_imagen(): void
    {
        $this->comoEncargado(self::COMERCIO);
        $idProducto = $this->productoEnBorrador();

        $this->patchJson(self::BASE."/productos/{$idProducto}", ['estado_producto' => 'publicado'])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonStructure(['error' => ['detalles' => ['variaciones', 'imagenes']]]);
    }

    public function test_la_imagen_se_puede_subir_como_archivo(): void
    {
        Storage::fake('public');
        $this->comoEncargado(self::COMERCIO);
        $idProducto = $this->productoEnBorrador();

        $respuesta = $this->post(self::BASE."/productos/{$idProducto}/imagenes", [
            'archivo' => UploadedFile::fake()->create('foto.jpg', 120, 'image/jpeg'),
        ], ['Accept' => 'application/json']);

        $respuesta->assertCreated()->assertJsonPath('id_producto', $idProducto);
        $this->assertNotEmpty($respuesta->json('imagen_url'));
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    // ---------------------------------------------------------------- reglas

    public function test_rechaza_un_id_comercio_diferente_al_de_la_sesion(): void
    {
        $this->comoEncargado(self::COMERCIO);

        $idCategoria = $this->postJson(self::BASE.'/categorias', [
            'nombre' => 'Hogar',
        ])->assertCreated()->json('id_categoria');

        $this->postJson(self::BASE.'/productos', [
            'id_comercio' => self::OTRO_COMERCIO,
            'id_categoria' => $idCategoria,
            'nombre' => 'Taza',
        ])
            ->assertForbidden()
            ->assertJsonPath('error.codigo', 'ERR-002');
    }

    public function test_acepta_el_id_comercio_cuando_coincide_con_la_sesion(): void
    {
        $this->comercioActivoEnCatalogo(self::COMERCIO);
        $this->comoEncargado(self::COMERCIO);

        $idCategoria = $this->postJson(self::BASE.'/categorias', [
            'nombre' => 'Electrónica',
        ])->assertCreated()->json('id_categoria');

        $this->postJson(self::BASE.'/productos', [
            'id_comercio' => self::COMERCIO,
            'id_categoria' => $idCategoria,
            'nombre' => 'Audífonos',
        ])
            ->assertCreated()
            ->assertJsonPath('id_comercio', self::COMERCIO);
    }

    public function test_acepta_el_producto_sin_enviar_id_comercio(): void
    {
        $this->comercioActivoEnCatalogo(self::COMERCIO);
        $this->comoEncargado(self::COMERCIO);

        $idCategoria = $this->postJson(self::BASE.'/categorias', [
            'nombre' => 'Accesorios',
        ])->assertCreated()->json('id_categoria');

        $this->postJson(self::BASE.'/productos', [
            'id_categoria' => $idCategoria,
            'nombre' => 'Reloj',
        ])
            ->assertCreated()
            ->assertJsonPath('id_comercio', self::COMERCIO);
    }

    public function test_los_datos_invalidos_responden_422_con_el_detalle_por_campo(): void
    {
        $this->comoEncargado(self::COMERCIO);

        $this->postJson(self::BASE.'/productos', ['nombre' => ''])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonStructure(['error' => ['detalles' => ['id_categoria', 'nombre']]]);
        $this->postJson(self::BASE.'/categorias', [])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005');
    }

    public function test_actualiza_disponibilidad_de_una_variacion_y_el_get_refleja_el_cambio(): void
    {
        $this->comoEncargado(self::COMERCIO);

        $idProducto = $this->productoEnBorrador();

        $idVariacion = $this->postJson(
            self::BASE."/productos/{$idProducto}/variaciones",
            [
                'sku' => 'TEST-DISP-01',
                'precio' => 5000,
                'tarifa_impuesto' => 13,
                'disponible' => true,
            ]
        )
            ->assertCreated()
            ->assertJsonPath('disponible', true)
            ->json('id_variacion');

        $this->patchJson(
            self::BASE."/variaciones/{$idVariacion}",
            ['disponible' => false]
        )
            ->assertOk()
            ->assertJsonPath('id_variacion', $idVariacion)
            ->assertJsonPath('disponible', false);

        $this->getJson(self::BASE."/variaciones/{$idVariacion}")
            ->assertOk()
            ->assertJsonPath('disponible', false);
    }

    public function test_no_autenticado_no_puede_actualizar_disponibilidad(): void
    {
        $this->comoEncargado(self::COMERCIO);
        $idProducto = $this->productoEnBorrador();

        $idVariacion = $this->postJson(
            self::BASE."/productos/{$idProducto}/variaciones",
            [
                'sku' => 'TEST-401',
                'precio' => 5000,
                'tarifa_impuesto' => 13,
            ]
        )->assertCreated()->json('id_variacion');

        $this->app['auth']->forgetGuards();

        $this->patchJson(self::BASE."/variaciones/{$idVariacion}", [
            'disponible' => false,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.codigo', 'ERR-001');
    }

    public function test_un_cliente_no_puede_actualizar_disponibilidad(): void
    {
        $this->comoEncargado(self::COMERCIO);
        $idProducto = $this->productoEnBorrador();

        $idVariacion = $this->postJson(
            self::BASE."/productos/{$idProducto}/variaciones",
            [
                'sku' => 'TEST-403',
                'precio' => 5000,
                'tarifa_impuesto' => 13,
            ]
        )->assertCreated()->json('id_variacion');

        $this->como($this->cuenta(RolUsuario::Cliente));

        $this->patchJson(self::BASE."/variaciones/{$idVariacion}", [
            'disponible' => false,
        ])
            ->assertForbidden()
            ->assertJsonPath('error.codigo', 'ERR-002');
    }

    public function test_otro_comercio_no_puede_actualizar_disponibilidad_de_una_variacion(): void
    {
        $this->comoEncargado(self::COMERCIO);
        $idProducto = $this->productoEnBorrador();

        $idVariacion = $this->postJson(
            self::BASE."/productos/{$idProducto}/variaciones",
            [
                'sku' => 'TEST-AJENO',
                'precio' => 5000,
                'tarifa_impuesto' => 13,
                'disponible' => true,
            ]
        )->assertCreated()->json('id_variacion');

        $this->comoEncargado(self::OTRO_COMERCIO);

        $this->patchJson(self::BASE."/variaciones/{$idVariacion}", [
            'disponible' => false,
        ])
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-004');
    }

    public function test_disponible_es_obligatorio_al_actualizar_una_variacion(): void
    {
        $this->comoEncargado(self::COMERCIO);
        $idProducto = $this->productoEnBorrador();

        $idVariacion = $this->postJson(
            self::BASE."/productos/{$idProducto}/variaciones",
            [
                'sku' => 'TEST-422',
                'precio' => 5000,
                'tarifa_impuesto' => 13,
            ]
        )->assertCreated()->json('id_variacion');

        $this->patchJson(self::BASE."/variaciones/{$idVariacion}", [])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonStructure([
                'error' => [
                    'detalles' => ['disponible'],
                ],
            ]);
    }

    public function test_disponible_rechaza_valores_que_no_son_booleanos_estrictos(): void
    {
        $this->comoEncargado(self::COMERCIO);
        $idProducto = $this->productoEnBorrador();

        $idVariacion = $this->postJson(
            self::BASE."/productos/{$idProducto}/variaciones",
            [
                'sku' => 'TEST-BOOLEAN',
                'precio' => 5000,
                'tarifa_impuesto' => 13,
                'disponible' => true,
            ]
        )->assertCreated()->json('id_variacion');

        $valoresInvalidos = [0, 1, 'true', 'false'];

        foreach ($valoresInvalidos as $valor) {
            $this->patchJson(
                self::BASE."/variaciones/{$idVariacion}",
                ['disponible' => $valor]
            )
                ->assertStatus(422)
                ->assertJsonPath('error.codigo', 'ERR-005')
                ->assertJsonStructure([
                    'error' => [
                        'detalles' => ['disponible'],
                    ],
                ]);
        }

        $this->getJson(self::BASE."/variaciones/{$idVariacion}")
            ->assertOk()
            ->assertJsonPath('disponible', true);
    }

    public function test_un_sku_repetido_en_el_comercio_responde_409(): void
    {
        $this->comoEncargado(self::COMERCIO);
        $idProducto = $this->productoEnBorrador();
        $variacion = ['sku' => 'TAZ-BLA-01', 'precio' => 3000, 'tarifa_impuesto' => 13];

        $this->postJson(self::BASE."/productos/{$idProducto}/variaciones", $variacion)->assertCreated();
        $this->postJson(self::BASE."/productos/{$idProducto}/variaciones", $variacion)
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M3-001');
    }

    public function test_otro_comercio_no_puede_tocar_el_producto(): void
    {
        $this->comoEncargado(self::COMERCIO);
        $idProducto = $this->productoEnBorrador();

        $this->comoEncargado(self::OTRO_COMERCIO);

        $this->postJson(self::BASE."/productos/{$idProducto}/variaciones", [
            'sku' => 'AJENO-01', 'precio' => 1000, 'tarifa_impuesto' => 13,
        ])->assertNotFound();
        $this->patchJson(self::BASE."/productos/{$idProducto}", ['destacado' => true])->assertNotFound();
    }

    public function test_solo_se_relacionan_productos_del_mismo_comercio(): void
    {
        $this->comoEncargado(self::COMERCIO);
        $idProducto = $this->productoEnBorrador();
        $idHermano = $this->productoEnBorrador();

        $this->postJson(self::BASE."/productos/{$idProducto}/relacionados", ['id_producto_relacionado' => $idHermano])
            ->assertCreated()
            ->assertJsonPath('id_producto_relacionado', $idHermano);
        $this->postJson(self::BASE."/productos/{$idProducto}/relacionados", ['id_producto_relacionado' => $idProducto])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-M3-002');
    }

    // ---------------------------------------------------------------- ayudantes

    /** @return list<array{0: string, 1: string}> */
    private function escrituras(): array
    {
        return [
            ['POST', '/categorias'],
            ['POST', '/marcas'],
            ['POST', '/productos'],
            ['PATCH', '/productos/1'],
            ['POST', '/productos/1/variaciones'],
            ['POST', '/productos/1/imagenes'],
            ['POST', '/productos/1/relacionados'],
        ];
    }

    private function productoEnBorrador(): int
    {
        $idCategoria = $this->postJson(self::BASE.'/categorias', ['nombre' => 'Categoría '.uniqid()])
            ->assertCreated()
            ->json('id_categoria');

        return $this->postJson(self::BASE.'/productos', ['id_categoria' => $idCategoria, 'nombre' => 'Producto de prueba'])
            ->assertCreated()
            ->json('id_producto');
    }

    private function comoEncargado(int $idComercio): static
    {
        return $this->como($this->cuenta(RolUsuario::EncargadoComercio, $idComercio));
    }

    /** Cuenta del núcleo en memoria; la sesión se simula con actingAs. */
    private function cuenta(RolUsuario $rol, ?int $idComercio = null): Usuario
    {
        $usuario = new Usuario;
        $usuario->forceFill([
            'id_usuario' => 9000 + ($idComercio ?? 0),
            'rol' => $rol,
            'id_comercio' => $idComercio,
            'activo' => true,
        ]);

        return $usuario;
    }

    /** El guard de Sanctum recuerda al primer usuario de la prueba: se olvida antes de cambiarlo. */
    private function como(Usuario $usuario): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($usuario, 'web');
    }

    /** Proyección del estado del comercio, de la que sale la visibilidad pública. */
    private function crearEstadoDeComercios(): void
    {
        if (Schema::hasTable('estado_comercios_catalogo')) {
            return;
        }

        Schema::create('estado_comercios_catalogo', function (Blueprint $tabla) {
            $tabla->unsignedBigInteger('id_comercio')->primary();
            $tabla->boolean('activo')->default(false);
            $tabla->timestamp('fecha_ultimo_evento')->nullable();
            $tabla->uuid('id_ultimo_evento')->nullable();
            $tabla->timestamps();
        });
    }

    private function comercioActivoEnCatalogo(int $idComercio): void
    {
        DB::table('estado_comercios_catalogo')->insert([
            'id_comercio' => $idComercio,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
