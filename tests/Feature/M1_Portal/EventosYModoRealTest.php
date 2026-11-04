<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Contracts\M5_Pedidos\CarritoServiceInterface;
use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Events\M4_Inventario\ExistenciaRepuesta;
use App\Listeners\M1_Portal\EventosComercioListener;
use App\Listeners\M1_Portal\EventosExistenciaListener;
use App\Providers\M1IntegracionesServiceProvider;
use App\Services\M1_Portal\Dobles\CarritoFake;
use App\Services\M1_Portal\Dobles\CatalogoFake;
use App\Services\M1_Portal\Dobles\ComerciosStub;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M1_Portal\Dobles\InventarioStub;
use App\Services\M1_Portal\DTO\Disponibilidad;
use App\Services\M1_Portal\Integraciones\CachePortal;
use App\Services\M1_Portal\Integraciones\CarritoClient;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use App\Services\M1_Portal\Integraciones\ComercioClient;
use App\Services\M1_Portal\Integraciones\InventarioClient;
use App\Services\M2_Comercios\ComerciosService;
use App\Services\M3_Catalogo\CatalogoService;
use App\Services\M4_Inventario\InventarioService;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Tarea 19: cada proveedor pasa del doble al servicio real solo con
 * config/integraciones.php. Tarea 20: los listeners de los eventos de M2 y M4
 * actualizan la caché del portal (x-eventos-consumidos del contrato).
 */
class EventosYModoRealTest extends TestCase
{
    // ---------------------------------------------------------------
    // Tarea 19: dobles y servicios reales
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{class-string, string, class-string, string, class-string}>
     */
    public static function adaptadores(): array
    {
        return [
            'M2' => [ComercioClient::class, 'm2', ComerciosServiceInterface::class, 'contrato', ComerciosStub::class],
            'M3' => [CatalogoClient::class, 'm3', CatalogoServiceInterface::class, 'contrato', CatalogoFake::class],
            'M4' => [InventarioClient::class, 'm4', InventarioModuleInterface::class, 'contrato', InventarioStub::class],
            'M5' => [CarritoClient::class, 'm5', CarritoServiceInterface::class, 'contrato', CarritoFake::class],
        ];
    }

    /**
     * @param  class-string  $adaptador
     * @param  class-string  $interfaz
     * @param  class-string  $doble
     */
    #[DataProvider('adaptadores')]
    public function test_cada_adaptador_recibe_el_doble_de_su_proveedor_por_defecto(string $adaptador, string $clave, string $interfaz, string $propiedad, string $doble): void
    {
        $this->assertContains($adaptador, M1IntegracionesServiceProvider::CONSUMIDORES_M1);
        $this->assertInstanceOf($doble, $this->proveedorDe(app($adaptador), $propiedad));
        $this->assertInstanceOf($interfaz, $this->proveedorDe(app($adaptador), $propiedad));
    }

    /**
     * @return array<string, array{class-string, string, class-string}>
     */
    public static function serviciosReales(): array
    {
        return [
            'M2' => [ComercioClient::class, 'm2', ComerciosService::class],
            'M3' => [CatalogoClient::class, 'm3', CatalogoService::class],
            'M4' => [InventarioClient::class, 'm4', InventarioService::class],
        ];
    }

    /**
     * @param  class-string  $adaptador
     * @param  class-string  $real
     */
    #[DataProvider('serviciosReales')]
    public function test_cambiar_el_modo_a_real_entrega_el_servicio_real_sin_tocar_el_adaptador(string $adaptador, string $clave, string $real): void
    {
        config(["integraciones.m1.{$clave}.modo" => 'real']);

        $this->assertInstanceOf($real, $this->proveedorDe(app($adaptador), 'contrato'));
    }

    public function test_el_modo_real_de_m5_usa_el_servicio_que_m5_enlace_a_carrito_service_interface(): void
    {
        $carritoReal = new CarritoFake;
        $this->app->instance(CarritoServiceInterface::class, $carritoReal);
        config(['integraciones.m1.m5.modo' => 'real']);

        $this->assertSame($carritoReal, $this->proveedorDe(app(CarritoClient::class), 'contrato'));
    }

    public function test_un_modo_desconocido_se_rechaza(): void
    {
        config(['integraciones.m1.m3.modo' => 'otro']);

        $this->expectException(InvalidArgumentException::class);

        app(CatalogoClient::class);
    }

    public function test_los_demas_modulos_siguen_recibiendo_los_servicios_reales(): void
    {
        $this->assertInstanceOf(ComerciosService::class, app(ComerciosServiceInterface::class));
        $this->assertInstanceOf(CatalogoService::class, app(CatalogoServiceInterface::class));
    }

    // ---------------------------------------------------------------
    // Tarea 20: listeners de eventos
    // ---------------------------------------------------------------

    public function test_los_listeners_quedan_registrados_para_los_eventos_de_m2_y_m4(): void
    {
        $eventosM2 = config('integraciones.m1.eventos_m2');

        $this->assertTrue(Event::hasListeners($eventosM2['comercio_aprobado']));
        $this->assertTrue(Event::hasListeners($eventosM2['comercio_suspendido']));
        $this->assertTrue(Event::hasListeners($eventosM2['comercio_reactivado']));
        $this->assertTrue(Event::hasListeners(ExistenciaAgotada::class));
        $this->assertTrue(Event::hasListeners(ExistenciaRepuesta::class));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function eventosDeComercio(): array
    {
        return [
            'ComercioAprobado' => ['comercio_aprobado'],
            'ComercioSuspendido' => ['comercio_suspendido'],
            'ComercioReactivado' => ['comercio_reactivado'],
        ];
    }

    #[DataProvider('eventosDeComercio')]
    public function test_cada_evento_de_comercio_de_m2_invalida_la_cache_del_comercio(string $evento): void
    {
        $cache = app(CachePortal::class);
        app(ComercioClient::class)->obtenerDatosComercioPublico(DatosPrueba::COMERCIO_LA_CEIBA);
        $clave = sprintf(CachePortal::CLAVE_COMERCIO_PUBLICO, DatosPrueba::COMERCIO_LA_CEIBA);
        $this->assertNotNull($cache->obtener($clave));

        Event::dispatch(config("integraciones.m1.eventos_m2.{$evento}"), [new class
        {
            public int $idComercio = DatosPrueba::COMERCIO_LA_CEIBA;
        }]);

        $this->assertNull($cache->obtener($clave));
    }

    public function test_el_listener_de_comercio_acepta_el_id_en_el_payload_como_los_eventos_de_m4(): void
    {
        $cache = app(CachePortal::class);
        $clave = sprintf(CachePortal::CLAVE_COMERCIO_RESUMEN, 35);
        $cache->guardar($clave, ['id_comercio' => 35]);

        app(EventosComercioListener::class)->alSuspenderComercio(new class
        {
            /**
             * @return array{id_comercio: int}
             */
            public function payload(): array
            {
                return ['id_comercio' => 35];
            }
        });

        $this->assertNull($cache->obtener($clave));
    }

    public function test_un_evento_de_comercio_sin_id_no_invalida_nada_ni_falla(): void
    {
        $cache = app(CachePortal::class);
        $cache->guardar(sprintf(CachePortal::CLAVE_COMERCIO_PUBLICO, 34), ['id_comercio' => 34]);

        app(EventosComercioListener::class)->alAprobarComercio(new class {});

        $this->assertNotNull($cache->obtener(sprintf(CachePortal::CLAVE_COMERCIO_PUBLICO, 34)));
    }

    public function test_existencia_agotada_de_m4_marca_la_variacion_como_agotada_en_el_portal(): void
    {
        $inventario = app(InventarioClient::class);
        $estado = fn (): Disponibilidad => $inventario->consultarDisponibilidadVariaciones(
            DatosPrueba::PRODUCTO_CAFE_MOLIDO,
            DatosPrueba::COMERCIO_CAFE_NICOYANO,
            [DatosPrueba::VARIACION_CAFE_MOLIDO],
        )[0]->estado;
        $this->assertSame(Disponibilidad::Disponible, $estado());

        ExistenciaAgotada::dispatch(DatosPrueba::COMERCIO_CAFE_NICOYANO, DatosPrueba::PRODUCTO_CAFE_MOLIDO, DatosPrueba::VARIACION_CAFE_MOLIDO);

        $this->assertSame(Disponibilidad::Agotado, $estado());
    }

    public function test_existencia_repuesta_de_m4_quita_la_marca_de_agotado(): void
    {
        $listener = app(EventosExistenciaListener::class);
        $listener->alAgotarExistencia(new ExistenciaAgotada(DatosPrueba::COMERCIO_CAFE_NICOYANO, DatosPrueba::PRODUCTO_CAFE_MOLIDO, DatosPrueba::VARIACION_CAFE_MOLIDO));

        ExistenciaRepuesta::dispatch(DatosPrueba::COMERCIO_CAFE_NICOYANO, DatosPrueba::PRODUCTO_CAFE_MOLIDO, DatosPrueba::VARIACION_CAFE_MOLIDO, 5);

        $estado = app(InventarioClient::class)->consultarDisponibilidadVariaciones(
            DatosPrueba::PRODUCTO_CAFE_MOLIDO,
            DatosPrueba::COMERCIO_CAFE_NICOYANO,
            [DatosPrueba::VARIACION_CAFE_MOLIDO],
        )[0]->estado;

        $this->assertSame(Disponibilidad::Disponible, $estado);
    }

    /**
     * Proveedor que el contenedor inyectó en el adaptador.
     */
    private function proveedorDe(object $adaptador, string $propiedad): object
    {
        return (new ReflectionProperty($adaptador, $propiedad))->getValue($adaptador);
    }
}
