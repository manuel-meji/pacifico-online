<?php

namespace Tests\Unit\M1_Portal;

use App\Models\M1_Portal\BusquedaUsuario;
use App\Models\M1_Portal\CategoriaPortal;
use App\Models\M1_Portal\Cliente;
use App\Models\M1_Portal\ComercioDestacado;
use App\Models\M1_Portal\Cupon;
use App\Models\M1_Portal\CuponUso;
use App\Models\M1_Portal\DireccionCliente;
use App\Models\M1_Portal\FiltroAplicado;
use App\Models\M1_Portal\NoticiaPortal;
use App\Models\M1_Portal\ProductoDestacado;
use App\Models\M1_Portal\PromocionPortal;
use App\Services\M1_Portal\DTO\ClienteDTO;
use App\Services\M1_Portal\DTO\DireccionClienteDTO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lógica de los modelos de M1 (Vista 5 del PC3). No usan la base de datos: las
 * migraciones de M1 tienen restricciones de PostgreSQL que no corren en el
 * SQLite de las pruebas, así que los modelos se arman en memoria.
 */
class ModelosTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, array{class-string, string, string}>
     */
    public static function tablasYClaves(): array
    {
        return [
            'clientes' => [Cliente::class, 'clientes', 'id_cliente'],
            'direcciones_cliente' => [DireccionCliente::class, 'direcciones_cliente', 'id_direccion_cliente'],
            'categorias_portal' => [CategoriaPortal::class, 'categorias_portal', 'id_categoria_portal'],
            'productos_destacados' => [ProductoDestacado::class, 'productos_destacados', 'id_producto_destacado'],
            'comercios_destacados' => [ComercioDestacado::class, 'comercios_destacados', 'id_comercio_destacado'],
            'promociones_portal' => [PromocionPortal::class, 'promociones_portal', 'id_promocion_portal'],
            'noticias_portal' => [NoticiaPortal::class, 'noticias_portal', 'id_noticia_portal'],
            'busquedas_usuario' => [BusquedaUsuario::class, 'busquedas_usuario', 'id_busqueda_usuario'],
            'filtros_aplicados' => [FiltroAplicado::class, 'filtros_aplicados', 'id_filtro_aplicado'],
            'cupones' => [Cupon::class, 'cupones', 'id_cupon'],
            'cupon_usos' => [CuponUso::class, 'cupon_usos', 'id_cupon_uso'],
        ];
    }

    /**
     * @param  class-string<Model>  $modelo
     */
    #[DataProvider('tablasYClaves')]
    public function test_cada_modelo_usa_su_tabla_y_clave_de_la_migracion(string $modelo, string $tabla, string $clave): void
    {
        $instancia = new $modelo;

        $this->assertSame($tabla, $instancia->getTable());
        $this->assertSame($clave, $instancia->getKeyName());
    }

    public function test_busquedas_y_filtros_no_usan_marcas_de_tiempo_porque_la_tabla_no_las_tiene(): void
    {
        $this->assertFalse((new BusquedaUsuario)->usesTimestamps());
        $this->assertFalse((new FiltroAplicado)->usesTimestamps());
        $this->assertTrue((new Cliente)->usesTimestamps());
    }

    public function test_las_factories_generan_modelos_que_cumplen_las_restricciones(): void
    {
        $promocion = PromocionPortal::factory()->make();
        $noticia = NoticiaPortal::factory()->make();
        $filtro = FiltroAplicado::factory()->make(['id_busqueda_usuario' => 1]);

        $this->assertContains($promocion->tipo_destino, PromocionPortal::TIPOS_DESTINO);
        $this->assertTrue($promocion->fecha_fin->greaterThan($promocion->fecha_inicio));
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $noticia->slug_noticia);
        $this->assertContains($filtro->tipo_filtro, FiltroAplicado::TIPOS);
        $this->assertSame('activo', Cliente::factory()->make(['id_usuario' => 1])->estado_cliente);
    }

    public function test_un_cliente_nuevo_queda_activo_y_una_direccion_nueva_no_es_principal(): void
    {
        $this->assertSame(Cliente::ESTADO_ACTIVO, (new Cliente)->estado_cliente);
        $this->assertFalse((new DireccionCliente)->es_principal);
    }

    public function test_direccion_principal_devuelve_la_marcada_como_principal(): void
    {
        $cliente = $this->cliente();
        $casa = $this->direccion(['id_direccion_cliente' => 10, 'alias_direccion' => 'Casa', 'es_principal' => false]);
        $trabajo = $this->direccion(['id_direccion_cliente' => 11, 'alias_direccion' => 'Trabajo', 'es_principal' => true]);
        $cliente->setRelation('direcciones', collect([$casa, $trabajo]));

        $this->assertSame(11, $cliente->direccionPrincipal()?->id_direccion_cliente);
    }

    public function test_direccion_principal_es_nula_si_no_hay_ninguna_principal(): void
    {
        $cliente = $this->cliente();
        $cliente->setRelation('direcciones', collect([$this->direccion(['es_principal' => false])]));

        $this->assertNull($cliente->direccionPrincipal());
    }

    public function test_cliente_a_dto_expone_sus_datos_y_la_direccion_principal_en_texto(): void
    {
        $cliente = $this->cliente();
        $cliente->setRelation('direcciones', collect([$this->direccion(['es_principal' => true])]));

        $dto = $cliente->aDTO();

        $this->assertInstanceOf(ClienteDTO::class, $dto);
        $this->assertSame(5, $dto->idCliente);
        $this->assertSame('Ana', $dto->nombre);
        $this->assertSame('Mora Rojas', $dto->apellidos);
        $this->assertSame('ana@example.com', $dto->correoElectronico);
        $this->assertSame('5-0123-0456', $dto->cedula);
        $this->assertSame('200 m norte del parque, Liberia, Liberia, Guanacaste', $dto->direccionEnvio);
    }

    public function test_cliente_sin_direcciones_expone_direccion_de_envio_vacia(): void
    {
        $cliente = $this->cliente();
        $cliente->setRelation('direcciones', collect());

        $this->assertSame('', $cliente->aDTO()->direccionEnvio);
    }

    public function test_direccion_a_dto_conserva_todos_los_campos(): void
    {
        $dto = $this->direccion(['id_direccion_cliente' => 3, 'es_principal' => true])->aDTO();

        $this->assertInstanceOf(DireccionClienteDTO::class, $dto);
        $this->assertSame(3, $dto->idDireccionCliente);
        $this->assertSame(5, $dto->idCliente);
        $this->assertSame('Casa', $dto->aliasDireccion);
        $this->assertSame('Guanacaste', $dto->provincia);
        $this->assertTrue($dto->esPrincipal);
    }

    /**
     * @param  class-string<ProductoDestacado|ComercioDestacado|PromocionPortal>  $modelo
     */
    #[DataProvider('modelosConVigencia')]
    public function test_esta_vigente_solo_dentro_del_periodo_y_si_esta_activo(string $modelo, string $campoActivo): void
    {
        $destacado = new $modelo([
            'fecha_inicio' => '2026-11-01 00:00:00',
            'fecha_fin' => '2026-11-30 23:59:59',
            $campoActivo => true,
        ]);

        $this->assertFalse($destacado->estaVigente(Carbon::parse('2026-10-31 23:59:59')));
        $this->assertTrue($destacado->estaVigente(Carbon::parse('2026-11-01 00:00:00')));
        $this->assertTrue($destacado->estaVigente(Carbon::parse('2026-11-15 12:00:00')));
        $this->assertTrue($destacado->estaVigente(Carbon::parse('2026-11-30 23:59:59')));
        $this->assertFalse($destacado->estaVigente(Carbon::parse('2026-12-01 00:00:00')));

        $destacado->{$campoActivo} = false;

        $this->assertFalse($destacado->estaVigente(Carbon::parse('2026-11-15 12:00:00')));
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function modelosConVigencia(): array
    {
        return [
            'producto destacado' => [ProductoDestacado::class, 'activo'],
            'comercio destacado' => [ComercioDestacado::class, 'activo'],
            'promoción (RN-M1-10)' => [PromocionPortal::class, 'activa'],
        ];
    }

    /**
     * @return array<string, array{string, string, ?int}>
     */
    public static function destinosDePromocion(): array
    {
        return [
            'producto' => ['producto', 'id_producto', 1520],
            'comercio' => ['comercio', 'id_comercio', 34],
            'categoria' => ['categoria', 'id_categoria', 10],
        ];
    }

    #[DataProvider('destinosDePromocion')]
    public function test_promocion_a_dto_toma_el_destino_segun_su_tipo(string $tipo, string $campo, int $id): void
    {
        $promocion = PromocionPortal::factory()->make([
            'id_promocion_portal' => 7,
            'tipo_destino' => $tipo,
            'id_producto' => null,
            $campo => $id,
        ]);

        $dto = $promocion->aDTO();

        $this->assertSame(7, $dto->idPromocion);
        $this->assertSame($tipo, $dto->tipoDestino);
        $this->assertSame($id, $dto->idDestino);
    }

    public function test_promocion_de_enlace_no_tiene_id_de_destino(): void
    {
        $dto = PromocionPortal::factory()->make([
            'tipo_destino' => 'enlace',
            'id_producto' => null,
            'enlace_url' => 'https://pacificos.example/feria',
        ])->aDTO();

        $this->assertNull($dto->idDestino);
        $this->assertSame('https://pacificos.example/feria', $dto->enlaceUrl);
    }

    public function test_noticia_publicada_solo_con_estado_publicada_y_dentro_de_su_periodo(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00');
        $noticia = NoticiaPortal::factory()->make([
            'estado_noticia' => NoticiaPortal::ESTADO_PUBLICADA,
            'fecha_inicio' => '2026-11-01 00:00:00',
            'fecha_fin' => '2026-11-20 00:00:00',
        ]);

        $this->assertTrue($noticia->estaPublicada());

        $noticia->estado_noticia = NoticiaPortal::ESTADO_BORRADOR;
        $this->assertFalse($noticia->estaPublicada());

        $noticia->estado_noticia = NoticiaPortal::ESTADO_PUBLICADA;
        Carbon::setTestNow('2026-11-21 00:00:00');
        $this->assertFalse($noticia->estaPublicada());
    }

    public function test_noticia_a_dto_usa_fecha_inicio_como_fecha_de_publicacion(): void
    {
        $noticia = NoticiaPortal::factory()->make([
            'id_noticia_portal' => 4,
            'slug_noticia' => 'feria-de-artesania',
            'fecha_inicio' => '2026-11-01 08:00:00',
        ]);

        $dto = $noticia->aDTO();

        $this->assertSame(4, $dto->idNoticia);
        $this->assertSame('feria-de-artesania', $dto->slug);
        $this->assertSame('2026-11-01 08:00:00', $dto->fechaPublicacion?->format('Y-m-d H:i:s'));
    }

    private function cliente(): Cliente
    {
        return (new Cliente([
            'id_usuario' => 9,
            'cedula' => '5-0123-0456',
            'nombre' => 'Ana',
            'apellidos' => 'Mora Rojas',
            'correo_electronico' => 'ana@example.com',
            'telefono' => '8888-0000',
        ]))->forceFill(['id_cliente' => 5]);
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    private function direccion(array $valores = []): DireccionCliente
    {
        return (new DireccionCliente)->forceFill([
            'id_direccion_cliente' => 1,
            'id_cliente' => 5,
            'alias_direccion' => 'Casa',
            'nombre_destinatario' => 'Ana Mora',
            'telefono_destinatario' => '8888-0000',
            'provincia' => 'Guanacaste',
            'canton' => 'Liberia',
            'distrito' => 'Liberia',
            'otras_senas' => '200 m norte del parque',
            'es_principal' => false,
            ...$valores,
        ]);
    }
}
