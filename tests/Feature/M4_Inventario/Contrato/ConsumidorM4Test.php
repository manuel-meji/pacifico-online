<?php

namespace Tests\Feature\M4_Inventario\Contrato;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\Dependencias\CatalogoDependencia;
use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Contracts\M4_Inventario\Dependencias\PedidosDependencia;
use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Services\M4_Inventario\Dobles\CatalogoStub;
use App\Services\M4_Inventario\Dobles\ComerciosStub;
use App\Services\M4_Inventario\Dobles\PedidosStub;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use RuntimeException;
use Tests\Doubles\M4\CatalogoContratoM3;
use Tests\Doubles\M4\ComerciosContratoM2;
use Tests\Doubles\M4\NucleoContratoFake;
use Tests\Doubles\M4\PedidosContratoM5;
use Tests\TestCase;

/**
 * Pruebas de contrato de M4 como consumidor (sección 7.2 del PC4): cada dependencia de M4
 * se prueba con su adaptador real (ComerciosReal, CatalogoReal, PedidosReal, NucleoReal)
 * contra el doble del proveedor en la versión publicada (M2 v1.2.1, M3 v1.2.1, M5 v1.1.0 y
 * núcleo). Si un proveedor cambia su respuesta, la prueba falla aquí antes de romper a M4.
 * También verifica que los dobles por defecto de M4 entreguen la misma forma.
 *
 * Convención de nombre y ubicación: tests/Feature/<Modulo>/Contrato/Consumidor<Modulo>Test.php.
 */
class ConsumidorM4Test extends TestCase
{
    /** @param  list<string>  $claves */
    private function assertForma(array $claves, array $respuesta): void
    {
        foreach ($claves as $clave) {
            $this->assertArrayHasKey($clave, $respuesta, "Falta {$clave} en la respuesta del proveedor.");
        }
    }

    // ---------------------------------------------------------------- M2 v1.2.1

    public function test_m2_obtener_comercio_activo(): void
    {
        $this->app->instance(ComerciosServiceInterface::class, new ComerciosContratoM2);
        config(['integraciones.m4.m2.modo' => 'real']);
        $m2 = app(ComerciosDependencia::class);

        $activo = $m2->obtenerComercioActivo(104);
        $this->assertForma(['id_comercio', 'estado', 'activo', 'motivo_estado'], $activo);
        $this->assertSame([104, 'Activo', true], [$activo['id_comercio'], $activo['estado'], $activo['activo']]);
        $this->assertFalse($m2->obtenerComercioActivo(105)['activo'], 'Suspendido no está activo.');
        $this->assertFalse($m2->obtenerComercioActivo(999)['activo'], 'ERR-004 de M2 se trata como comercio no habilitado.');
        $this->assertForma(['id_comercio', 'estado', 'activo', 'motivo_estado'], (new ComerciosStub)->obtenerComercioActivo(1));
    }

    public function test_m2_obtener_sedes_comercio(): void
    {
        $this->app->instance(ComerciosServiceInterface::class, new ComerciosContratoM2);
        config(['integraciones.m4.m2.modo' => 'real']);
        $sedes = app(ComerciosDependencia::class)->obtenerSedesComercio(104);

        $this->assertSame(104, $sedes['id_comercio']);
        $this->assertSame([12, 13, 14], array_column($sedes['sedes'], 'id_sede'));
        $this->assertSame([true, true, false], array_column($sedes['sedes'], 'habilitada'));
        $this->assertSame([], app(ComerciosDependencia::class)->obtenerSedesComercio(999)['sedes']);
        $this->assertForma(['id_sede', 'habilitada'], (new ComerciosStub)->obtenerSedesComercio(1)['sedes'][0]);
    }

    public function test_m2_caido_responde_err_006(): void
    {
        $this->app->instance(ComerciosServiceInterface::class, new class extends ComerciosContratoM2
        {
            public function obtenerComercioActivo(int $idComercio): array
            {
                throw new RuntimeException('Conexión rechazada');
            }
        });
        config(['integraciones.m4.m2.modo' => 'real']);

        $this->expectExceptionObject(ErrorInventarioException::dependenciaNoDisponible('M2'));
        app(ComerciosDependencia::class)->obtenerComercioActivo(104);
    }

    // ---------------------------------------------------------------- M3 v1.2.1

    public function test_m3_obtener_variacion(): void
    {
        $this->app->instance(CatalogoServiceInterface::class, new CatalogoContratoM3);
        config(['integraciones.m4.m3.modo' => 'real']);
        $m3 = app(CatalogoDependencia::class);

        $this->assertSame(['id_variacion' => 501, 'id_producto' => 301, 'id_comercio' => 104, 'sku' => 'CAM-PAC-AZ-M'], $m3->obtenerVariacion(501));
        $this->assertNull($m3->obtenerVariacion(9999));
        $this->assertForma(['id_variacion', 'id_producto', 'id_comercio', 'sku'], (new CatalogoStub)->obtenerVariacion(501));
    }

    // ---------------------------------------------------------------- M5 v1.1.0

    public function test_m5_obtener_subpedido(): void
    {
        $this->app->instance(SubpedidosServiceInterface::class, new PedidosContratoM5);
        config(['integraciones.m4.m5.modo' => 'real']);
        $m5 = app(PedidosDependencia::class);

        $this->assertSame(['id_subpedido' => 9101, 'id_pedido' => 9001, 'id_comercio' => 104, 'estado_subpedido' => 'pagado'], $m5->obtenerSubpedido(9101));
        $this->assertSame('pendiente', $m5->obtenerSubpedido(9102)['estado_subpedido']);
        $this->assertNull($m5->obtenerSubpedido(9999), 'ERR-004 de M5: el subpedido no existe.');
        $this->assertSame(array_keys($m5->obtenerSubpedido(9101)), array_keys((new PedidosStub)->registrar(1, 1, 1)->obtenerSubpedido(1)));
    }

    public function test_m5_caido_responde_err_006(): void
    {
        $this->app->instance(SubpedidosServiceInterface::class, new class extends PedidosContratoM5
        {
            public function obtenerSubpedido(int $idSubpedido, ?int $idComercioSesion = null): array
            {
                throw new RuntimeException('Tiempo de espera agotado');
            }
        });
        config(['integraciones.m4.m5.modo' => 'real']);

        try {
            app(PedidosDependencia::class)->obtenerSubpedido(9101);
            $this->fail('Debió responder ERR-006.');
        } catch (ErrorInventarioException $e) {
            $this->assertSame(['ERR-006', 503, 'M5'], [$e->codigo, $e->http, $e->detalles['modulo']]);
        }
    }

    // ---------------------------------------------------------------- Núcleo

    public function test_nucleo_entrega_la_sesion_el_usuario_y_la_bitacora(): void
    {
        $nucleo = new NucleoContratoFake(104);
        $this->app->instance(NucleoServiceInterface::class, $nucleo);
        config(['integraciones.m4.nucleo.modo' => 'real']);
        $dependencia = app(NucleoDependencia::class);

        $dependencia->registrarAuditoria('ajuste_inventario', 'ajustes_inventario', 7, ['modulo' => 'M4']);

        $this->assertSame(104, $dependencia->comercioActual());
        $this->assertSame(0, $dependencia->usuarioActual(), 'Sin usuario autenticado, el ejecutor es el sistema (0).');
        $this->assertSame([['accion' => 'ajuste_inventario', 'entidad' => 'ajustes_inventario', 'entidad_id' => 7, 'detalles' => ['modulo' => 'M4']]], $nucleo->bitacora);
    }
}
