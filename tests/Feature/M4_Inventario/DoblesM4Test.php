<?php

namespace Tests\Feature\M4_Inventario;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\Dependencias\CatalogoDependencia;
use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Contracts\M4_Inventario\Dependencias\VerificadorAutorizacionesInterface;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Services\M4_Inventario\Dobles\CatalogoStub;
use App\Services\M4_Inventario\Dobles\ComerciosStub;
use App\Services\M4_Inventario\Dobles\NucleoStub;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\Doubles\M4\AutorizacionesFake;
use Tests\Doubles\M4\CatalogoContratoM3;
use Tests\Doubles\M4\CatalogoDobleM4;
use Tests\Doubles\M4\CatalogoNoDisponible;
use Tests\Doubles\M4\ComerciosContratoM2;
use Tests\Doubles\M4\ComerciosDobleM4;
use Tests\Doubles\M4\ComerciosNoDisponible;
use Tests\Doubles\M4\DatosPruebaM4;
use Tests\Doubles\M4\ErrorProveedorException;
use Tests\Doubles\M4\EventosCatalogoSimulados;
use Tests\Doubles\M4\EventosComercioSimulados;
use Tests\Doubles\M4\Identificadores;
use Tests\Doubles\M4\NucleoContratoFake;
use Tests\Doubles\M4\NucleoDobleM4;
use Tests\Doubles\M4\PedidosContratoM5;
use Tests\Doubles\M4\PedidosNoDisponible;
use Tests\TestCase;

/**
 * Sección 9 del PC3 (SG4): los dobles de prueba de M2, M3, M5 y el núcleo respetan los contratos
 * que imitan, cubren éxito y error, se conectan al despliegue de M4 cambiando la clase en
 * config/integraciones.php (sin tocar el código de M4) y alimentan a los adaptadores reales
 * (ComerciosReal, CatalogoReal, NucleoReal). Los fixtures de eventos de M2 y M3 traen la carga
 * útil del contrato y permiten probar la idempotencia.
 */
class DoblesM4Test extends TestCase
{
    use EsquemaInventarioPrueba;

    /** Conecta los dobles de esta sección en lugar de los dobles por defecto del despliegue. */
    private function usarDoblesDePrueba(string $m2 = ComerciosDobleM4::class, string $m3 = CatalogoDobleM4::class): void
    {
        config([
            'integraciones.m4.m2.doble' => $m2,
            'integraciones.m4.m3.doble' => $m3,
            'integraciones.m4.nucleo.doble' => NucleoDobleM4::class,
        ]);
    }

    private function inventario(): InventarioModuleInterface
    {
        return app(InventarioModuleInterface::class);
    }

    // ---------------------------------------------------------------- conexión por configuración

    public function test_sin_cambiar_la_configuracion_siguen_los_dobles_por_defecto_del_despliegue(): void
    {
        $this->assertInstanceOf(ComerciosStub::class, app(ComerciosDependencia::class));
        $this->assertInstanceOf(CatalogoStub::class, app(CatalogoDependencia::class));
        $this->assertInstanceOf(NucleoStub::class, app(NucleoDependencia::class));
    }

    public function test_los_dobles_de_prueba_se_conectan_cambiando_la_clase_en_la_configuracion(): void
    {
        $this->usarDoblesDePrueba();

        $this->assertInstanceOf(ComerciosDobleM4::class, app(ComerciosDependencia::class));
        $this->assertInstanceOf(CatalogoDobleM4::class, app(CatalogoDependencia::class));
        $this->assertInstanceOf(NucleoDobleM4::class, app(NucleoDependencia::class));
    }

    // ---------------------------------------------------------------- dobles de las dependencias de M4

    public function test_m2_cubre_los_cuatro_estados_del_contrato(): void
    {
        $m2 = new ComerciosDobleM4;

        $this->assertSame(['id_comercio' => 104, 'estado' => 'Activo', 'motivo_estado' => null, 'activo' => true], $m2->obtenerComercioActivo(104));

        foreach ([105 => 'Suspendido', 106 => 'Pendiente', 107 => 'Rechazado'] as $idComercio => $estado) {
            $this->assertSame($estado, $m2->obtenerComercioActivo($idComercio)['estado']);
            $this->assertFalse($m2->obtenerComercioActivo($idComercio)['activo']);
        }

        $this->assertSame('Membresía vencida', $m2->obtenerComercioActivo(105)['motivo_estado']);
        $this->assertNull($m2->obtenerComercioActivo(106)['motivo_estado'], 'Pendiente no trae motivo.');
    }

    public function test_m2_sedes_con_su_bandera_y_comercio_sin_sedes(): void
    {
        $m2 = new ComerciosDobleM4;
        $sedes = $m2->obtenerSedesComercio(104)['sedes'];

        $this->assertSame([12, 13, 14], array_column($sedes, 'id_sede'));
        $this->assertSame([true, true, false], array_column($sedes, 'habilitada'));
        $this->assertSame([], $m2->obtenerSedesComercio(106)['sedes']);
    }

    public function test_m2_errores_de_comercio_inexistente_id_invalido_y_caida(): void
    {
        $m2 = new ComerciosDobleM4;

        $this->assertErrorInventario('ERR-003', 404, fn () => $m2->obtenerComercioActivo(999));
        $this->assertErrorInventario('ERR-003', 404, fn () => $m2->obtenerSedesComercio(999));
        $this->assertErrorInventario('ERR-005', 422, fn () => $m2->obtenerComercioActivo(0));
        $this->assertErrorInventario('ERR-006', 503, fn () => (new ComerciosNoDisponible)->obtenerComercioActivo(104), 'M2');
        $this->assertErrorInventario('ERR-006', 503, fn () => (new ComerciosNoDisponible)->obtenerSedesComercio(104), 'M2');
    }

    public function test_m3_variaciones_de_cada_comercio_inexistente_y_errores(): void
    {
        $m3 = new CatalogoDobleM4;

        $this->assertSame(['id_variacion' => 501, 'id_producto' => 301, 'id_comercio' => 104], $m3->obtenerVariacion(501));
        $this->assertSame(105, $m3->obtenerVariacion(601)['id_comercio']);
        $this->assertNull($m3->obtenerVariacion(999));
        $this->assertErrorInventario('ERR-005', 422, fn () => $m3->obtenerVariacion(0));
        $this->assertErrorInventario('ERR-006', 503, fn () => (new CatalogoNoDisponible)->obtenerVariacion(501), 'M3');
    }

    public function test_nucleo_sesion_configurable_y_bitacora_con_los_campos_del_comite(): void
    {
        $nucleo = new NucleoDobleM4(104, 7);
        $nucleo->registrarAuditoria('reserva_creada', 'reservas', 1, ['cantidad' => 2]);
        $registro = $nucleo->bitacora()[0];

        $this->assertSame(104, $nucleo->comercioActual());
        $this->assertSame(['id_usuario', 'fecha_hora', 'accion', 'modulo', 'entidad', 'entidad_id', 'detalles'], array_keys($registro));
        $this->assertSame(7, $registro['id_usuario']);
        $this->assertSame('M4', $registro['modulo']);
        $this->assertTrue($nucleo->fueRegistrada('reserva_creada', 'reservas'));

        $nucleo->actuarComo(null);
        $this->assertNull($nucleo->comercioActual(), 'Sin ámbito de comercio.');
    }

    public function test_la_bitacora_rechaza_registros_incompletos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NucleoDobleM4)->registrarAuditoria('', 'reservas', 1);
    }

    // ---------------------------------------------------------------- M4 con los dobles de prueba conectados

    public function test_con_los_dobles_de_prueba_m4_aplica_sus_reglas_antes_de_reservar(): void
    {
        $this->usarDoblesDePrueba();

        $this->assertErrorInventario('ERR-M4-015', 409, fn () => $this->inventario()->reservarExistencias(105, 601, 1));
        $this->assertErrorInventario('ERR-M4-015', 409, fn () => $this->inventario()->reservarExistencias(106, 501, 1));
        $this->assertErrorInventario('ERR-M4-015', 409, fn () => $this->inventario()->reservarExistencias(107, 501, 1));
        $this->assertErrorInventario('ERR-003', 404, fn () => $this->inventario()->reservarExistencias(999, 501, 1));
        $this->assertErrorInventario('ERR-M4-013', 404, fn () => $this->inventario()->reservarExistencias(104, 999, 1));
        $this->assertErrorInventario('ERR-003', 404, fn () => $this->inventario()->reservarExistencias(104, 601, 1));
        $this->assertErrorInventario('ERR-M4-014', 404, fn () => $this->inventario()->reservarExistencias(104, 501, 1, 14));
        $this->assertErrorInventario('ERR-M4-014', 404, fn () => $this->inventario()->reservarExistencias(104, 501, 1, 21));
    }

    public function test_con_un_proveedor_caido_m4_responde_err_006_con_el_modulo(): void
    {
        $this->usarDoblesDePrueba(m2: ComerciosNoDisponible::class);
        $this->assertErrorInventario('ERR-006', 503, fn () => $this->inventario()->reservarExistencias(104, 501, 1), 'M2');

        $this->usarDoblesDePrueba(m3: CatalogoNoDisponible::class);
        $this->assertErrorInventario('ERR-006', 503, fn () => $this->inventario()->reservarExistencias(104, 501, 1), 'M3');
    }

    public function test_con_los_dobles_de_prueba_la_reserva_se_completa_en_una_sede_habilitada(): void
    {
        $this->crearEsquemaInventario();
        $this->existencia(['id_comercio' => 104, 'id_producto' => 301, 'id_variacion' => 501, 'id_ubicacion' => 12, 'cantidad_fisica' => 3]);
        $this->usarDoblesDePrueba();
        Event::fake();

        $reserva = $this->inventario()->reservarExistencias(104, 501, 2);

        $this->assertSame('activa', $reserva['estado_reserva']);
        $this->assertSame(104, $reserva['id_comercio']);
        $this->assertSame(12, $reserva['id_ubicacion']);
        $this->assertErrorInventario('ERR-M4-001', 409, fn () => $this->inventario()->reservarExistencias(104, 501, 2));
    }

    // ---------------------------------------------------------------- adaptadores reales con dobles que imitan los contratos

    public function test_catalogo_real_traduce_la_variacion_del_contrato_de_m3(): void
    {
        $this->app->instance(CatalogoServiceInterface::class, new CatalogoContratoM3);
        config(['integraciones.m4.m3.modo' => 'real']);

        $catalogo = app(CatalogoDependencia::class);

        $this->assertSame(['id_variacion' => 501, 'id_producto' => 301, 'id_comercio' => 104], $catalogo->obtenerVariacion(501));
        $this->assertNull($catalogo->obtenerVariacion(999));
    }

    public function test_catalogo_real_responde_err_006_si_m3_no_responde(): void
    {
        $this->app->instance(CatalogoServiceInterface::class, new class extends CatalogoContratoM3
        {
            public function obtenerVariacion(int $variacionId): ?array
            {
                throw ErrorProveedorException::noDisponible('M3');
            }
        });
        config(['integraciones.m4.m3.modo' => 'real']);

        $this->assertErrorInventario('ERR-006', 503, fn () => app(CatalogoDependencia::class)->obtenerVariacion(501), 'M3');
    }

    public function test_comercios_real_usa_las_operaciones_del_contrato_de_m2(): void
    {
        $this->app->instance(ComerciosServiceInterface::class, new ComerciosContratoM2);
        config(['integraciones.m4.m2.modo' => 'real']);

        $comercios = app(ComerciosDependencia::class);

        $this->assertTrue($comercios->obtenerComercioActivo(104)['activo']);
        $this->assertFalse($comercios->obtenerComercioActivo(105)['activo']);
        $this->assertSame([12, 13, 14], array_column($comercios->obtenerSedesComercio(104)['sedes'], 'id_sede'));
    }

    public function test_nucleo_real_entrega_la_sesion_y_la_bitacora_al_nucleo(): void
    {
        $nucleo = new NucleoContratoFake(104);
        $this->app->instance(NucleoServiceInterface::class, $nucleo);
        config(['integraciones.m4.nucleo.modo' => 'real']);

        $dependencia = app(NucleoDependencia::class);
        $dependencia->registrarAuditoria('reserva_liberada', 'reservas', 5, ['motivo' => 'pago_rechazado']);

        $this->assertSame(104, $dependencia->comercioActual());
        $this->assertSame([['accion' => 'reserva_liberada', 'entidad' => 'reservas', 'entidad_id' => 5, 'detalles' => ['motivo' => 'pago_rechazado']]], $nucleo->bitacora);
    }

    // ---------------------------------------------------------------- dobles de contrato de M2, M3 y M5

    public function test_los_dobles_de_contrato_de_m2_y_m3_responden_los_errores_del_proveedor(): void
    {
        $this->assertErrorProveedor('ERR-004', fn () => (new ComerciosContratoM2)->obtenerComercioActivo(999));
        $this->assertErrorProveedor('ERR-005', fn () => (new ComerciosContratoM2)->obtenerSedesComercio(0));
        $this->assertErrorProveedor('ERR-005', fn () => (new CatalogoContratoM3)->obtenerVariacion(0));
        $this->assertSame(104, (new CatalogoContratoM3)->obtenerVariacion(501)['producto']['id_comercio']);
    }

    public function test_m5_pedido_pagado_pendiente_aislamiento_y_errores(): void
    {
        $m5 = new PedidosContratoM5;

        $this->assertSame('pagado', $m5->obtenerPedido(9001)['estado_pedido']);
        $this->assertCount(1, $m5->obtenerPedido(9001)['subpedidos']);
        $this->assertSame('pendiente', $m5->obtenerPedido(9002)['estado_pedido']);
        $this->assertSame(DatosPruebaM4::RESERVA_PAGADA, $m5->obtenerSubpedido(9101, 104)['lineas'][0]['id_reserva']);
        $this->assertErrorProveedor('ERR-003', fn () => $m5->obtenerSubpedido(9103, 104));
        $this->assertErrorProveedor('ERR-004', fn () => $m5->obtenerPedido(9999));
        $this->assertErrorProveedor('ERR-005', fn () => $m5->obtenerSubpedido(0));
        $this->assertErrorProveedor('ERR-006', fn () => (new PedidosNoDisponible)->obtenerSubpedido(9101), 'M5');
    }

    public function test_los_errores_de_los_proveedores_usan_el_formato_y_el_catalogo_del_nucleo(): void
    {
        foreach (ErrorProveedorException::CATALOGO_NUCLEO as $codigo => [$http, $mensaje]) {
            $error = ErrorProveedorException::delNucleo($codigo);

            $this->assertSame($http, $error->estadoHttp);
            $this->assertSame(['error' => ['codigo' => $codigo, 'mensaje' => $mensaje, 'detalles' => null]], $error->toArray());
        }

        $this->assertTrue(ErrorProveedorException::esCodigoDeModulo('ERR-M4-001'));
        $this->assertFalse(ErrorProveedorException::esCodigoDeModulo('ERR-001'));
        $this->expectException(InvalidArgumentException::class);
        ErrorProveedorException::delNucleo('ERR-M4-001');
    }

    // ---------------------------------------------------------------- verificador de autorizaciones (supuesto del núcleo)

    public function test_autorizaciones_validas_invalidas_y_de_un_solo_uso(): void
    {
        $fake = new AutorizacionesFake;
        $transferencia = VerificadorAutorizacionesInterface::OPERACION_TRANSFERENCIA;

        $this->assertSame(2, $fake->verificar('autorizacion-0001', $transferencia, 104, 501, [12, 13], 5)['id_autorizador']);
        $this->assertSame('autorizacion-0002', $fake->verificar('autorizacion-0002', VerificadorAutorizacionesInterface::OPERACION_AJUSTE, 104, 501, [12], 2)['id_autorizacion']);
        $this->assertErrorInventario('ERR-002', 403, fn () => $fake->verificar('autorizacion-vencida', $transferencia, 104, 501, [12, 13], 5));
        $this->assertErrorInventario('ERR-002', 403, fn () => $fake->verificar('autorizacion-otro-comercio', $transferencia, 104, 601, [21, 22], 1));
        $this->assertErrorInventario('ERR-002', 403, fn () => $fake->verificar('no-existe', 'ajuste', 104, 501, [12], 2));

        $fake->marcarUsada('autorizacion-0001');
        $this->assertErrorInventario('ERR-002', 403, fn () => $fake->verificar('autorizacion-0001', $transferencia, 104, 501, [12, 13], 5));
    }

    public function test_la_autorizacion_rechazada_explica_el_motivo(): void
    {
        try {
            (new AutorizacionesFake)->verificar('autorizacion-0001', 'transferencia', 104, 501, [13, 12], 5);
            $this->fail('Se esperaba ERR-002.');
        } catch (ErrorInventarioException $error) {
            $this->assertSame('La autorización no corresponde a la operación solicitada.', $error->getMessage());
            $this->assertSame(['id_autorizacion' => 'autorizacion-0001'], $error->detalles);
        }
    }

    // ---------------------------------------------------------------- fixtures de eventos de M2 y M3

    public function test_eventos_de_m2_traen_la_carga_util_del_contrato(): void
    {
        $aprobado = EventosComercioSimulados::comercioAprobado(104);
        $suspendido = EventosComercioSimulados::comercioSuspendido(104, 'Membresía vencida');
        $reactivado = EventosComercioSimulados::comercioReactivado(104);

        $this->assertSame(['nombre' => 'ComercioAprobado', 'version' => 'v1'], array_slice($aprobado, 0, 2));
        $this->assertSame(['id_evento', 'fecha_hora', 'id_comercio', 'estado', 'nombre_comercial'], array_keys($aprobado['carga_util']));
        $this->assertTrue(Identificadores::esUuid($aprobado['carga_util']['id_evento']));
        $this->assertSame('Membresía vencida', $suspendido['carga_util']['motivo']);
        $this->assertSame('Activo', $reactivado['carga_util']['estado']);
        $this->assertNotSame($aprobado['carga_util']['id_evento'], $reactivado['carga_util']['id_evento']);
    }

    public function test_la_suspension_exige_motivo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EventosComercioSimulados::comercioSuspendido(104, '   ');
    }

    public function test_evento_producto_publicado_y_duplicados_conservan_el_id_evento(): void
    {
        $evento = EventosCatalogoSimulados::productoPublicado(104, 301, 501);

        $this->assertSame('v1.0.0', $evento['version']);
        $this->assertSame(['id_evento', 'fecha_evento', 'id_comercio', 'id_producto', 'id_variacion'], array_keys($evento['carga_util']));
        $this->assertSame($evento, EventosCatalogoSimulados::duplicar($evento));

        $suspension = EventosComercioSimulados::comercioSuspendido(105);
        $this->assertSame($suspension['carga_util']['id_evento'], EventosComercioSimulados::duplicar($suspension)['carga_util']['id_evento']);

        $this->expectException(InvalidArgumentException::class);
        EventosCatalogoSimulados::productoPublicado(104, 0, 501);
    }

    // ---------------------------------------------------------------- utilidades

    private function assertErrorInventario(string $codigo, int $http, callable $accion, ?string $modulo = null): void
    {
        try {
            $accion();
        } catch (ErrorInventarioException $error) {
            $this->assertSame($codigo, $error->codigo, $error->getMessage());
            $this->assertSame($http, $error->http);

            if ($modulo !== null) {
                $this->assertSame($modulo, $error->detalles['modulo'] ?? null);
            }

            return;
        }

        $this->fail("Se esperaba {$codigo}.");
    }

    private function assertErrorProveedor(string $codigo, callable $accion, ?string $modulo = null): void
    {
        try {
            $accion();
        } catch (ErrorProveedorException $error) {
            $this->assertSame($codigo, $error->toArray()['error']['codigo'], $error->getMessage());
            $this->assertSame(ErrorProveedorException::CATALOGO_NUCLEO[$codigo][0], $error->estadoHttp);

            if ($modulo !== null) {
                $this->assertSame($modulo, $error->detalles['modulo'] ?? null);
            }

            return;
        }

        $this->fail("Se esperaba {$codigo}.");
    }
}
