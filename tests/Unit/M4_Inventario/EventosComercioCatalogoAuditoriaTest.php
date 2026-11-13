<?php

namespace Tests\Unit\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Events\M4_Inventario\ExistenciaRepuesta;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Doubles\M4\EventosCatalogoSimulados;
use Tests\Doubles\M4\EventosComercioSimulados;
use Tests\Feature\M4_Inventario\EsquemaInventarioPrueba;
use Tests\TestCase;

/**
 * Reglas de los eventos de M2 y M3 y de la consulta de auditoría (CP-M4-12 a CP-M4-14).
 * Los eventos se publican con el nombre de clase configurado en integraciones.m4.eventos
 * y la carga útil de los fixtures de la sección 9 (catálogo de eventos 4.3).
 */
class EventosComercioCatalogoAuditoriaTest extends TestCase
{
    use EsquemaInventarioPrueba;

    protected function setUp(): void
    {
        parent::setUp();
        config(['integraciones.m4.nucleo.modo' => 'real']);
        $this->crearEsquemaNucleo();
        $this->crearEsquemaInventario();
    }

    /** @param  array{nombre: string, carga_util: array<string, mixed>}  $evento */
    private function publicar(array $evento): void
    {
        $clave = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $evento['nombre']));
        event(config("integraciones.m4.eventos.{$clave}"), [(object) $evento['carga_util']]);
    }

    /** @return array<string, mixed> */
    private function entrada(int $comercio): array
    {
        return app(InventarioModuleInterface::class)->registrarMovimientoManual([
            'id_comercio' => $comercio, 'id_variacion' => 10, 'id_ubicacion' => 1,
            'tipo_movimiento' => 'entrada', 'cantidad' => 1, 'costo_unitario' => 100, 'justificacion' => 'Prueba',
        ]);
    }

    /** CP-M4-12 · RN-M4-005: ProductoPublicado crea la existencia en 0 en cada sede habilitada, una sola vez. */
    public function test_producto_publicado_crea_la_existencia_en_cero_sin_duplicar(): void
    {
        $evento = EventosCatalogoSimulados::productoPublicado(1, 301, 501);

        $this->publicar($evento);
        $this->publicar(EventosCatalogoSimulados::duplicar($evento));

        $filas = DB::table('existencias')->where('id_variacion', 501)->orderBy('id_ubicacion')->get();
        $this->assertSame([1, 2], $filas->pluck('id_ubicacion')->map(fn ($v): int => (int) $v)->all());
        $this->assertSame([0, 0], $filas->pluck('cantidad_fisica')->map(fn ($v): int => (int) $v)->all());
        $this->assertSame(0, DB::table('movimientos_inventario')->count(), 'No se inventan unidades físicas.');
        $this->assertSame('agotado', app(InventarioModuleInterface::class)->consultarStockDisponible(1, 501)['estado_disponibilidad']);
    }

    /** Acuerdo con M3: un producto publicado sin stock se avisa agotado una sola vez, y la primera entrada lo repone. */
    public function test_producto_publicado_sin_stock_se_avisa_agotado_y_la_primera_entrada_lo_repone(): void
    {
        Event::fake([ExistenciaAgotada::class, ExistenciaRepuesta::class]);
        $evento = EventosCatalogoSimulados::productoPublicado(1, 300, 10);

        $this->publicar($evento);
        $this->publicar(EventosCatalogoSimulados::duplicar($evento));

        Event::assertDispatchedTimes(ExistenciaAgotada::class, 1);
        Event::assertDispatched(ExistenciaAgotada::class, fn (ExistenciaAgotada $e): bool => [$e->idComercio, $e->idProducto, $e->idVariacion] === [1, 300, 10]);
        Event::assertNotDispatched(ExistenciaRepuesta::class);

        $this->entrada(1);

        Event::assertDispatchedTimes(ExistenciaRepuesta::class, 1);
    }

    /** Si la variación ya tiene unidades disponibles (por ejemplo, se vuelve a publicar), no se avisa agotada. */
    public function test_producto_publicado_con_stock_no_se_avisa_agotado(): void
    {
        $this->existencia(['id_producto' => 300, 'id_variacion' => 10, 'id_ubicacion' => 1, 'cantidad_fisica' => 5]);
        Event::fake([ExistenciaAgotada::class]);

        $this->publicar(EventosCatalogoSimulados::productoPublicado(1, 300, 10));

        Event::assertNotDispatched(ExistenciaAgotada::class);
    }

    /** CP-M4-13 · RN-M4-009: un comercio suspendido no registra movimientos; reactivado, sí. Un evento viejo no lo bloquea de nuevo. */
    public function test_comercio_suspendido_bloquea_los_movimientos_hasta_que_se_reactiva(): void
    {
        $suspendido = EventosComercioSimulados::comercioSuspendido(1, 'Documentos vencidos');
        $this->publicar($suspendido);

        try {
            $this->entrada(1);
            $this->fail('Debió responder ERR-M4-015.');
        } catch (ErrorInventarioException $e) {
            $this->assertSame(['ERR-M4-015', 409], [$e->codigo, $e->http]);
        }

        $this->publicar(EventosComercioSimulados::comercioReactivado(1));
        $this->publicar(EventosComercioSimulados::duplicar($suspendido)); // reenvío del evento anterior: sin efecto

        $this->assertSame('entrada', $this->entrada(1)['tipo_movimiento']);
    }

    /** CP-M4-14 · RN-M4-015: la auditoría ve varios comercios en solo lectura y queda en la bitácora; un comercio no puede usarla. */
    public function test_la_auditoria_consulta_varios_comercios_y_se_registra(): void
    {
        $this->existencia(['id_comercio' => 1, 'id_variacion' => 10, 'id_ubicacion' => 1, 'cantidad_fisica' => 4]);
        $this->existencia(['id_comercio' => 2, 'id_variacion' => 20, 'id_ubicacion' => 1, 'cantidad_fisica' => 6]);
        app(ContextoComercio::class)->concederAccesoAuditado();

        $resultado = app(InventarioModuleInterface::class)->consultarInventarioAuditoria([1, 2], '2026-01-01', '2026-12-31');

        $this->assertSame([1, 2], array_column($resultado['datos'], 'id_comercio'));
        $this->assertSame(6, $resultado['datos'][1]['existencias'][0]['cantidad_disponible']);
        $this->assertSame(1, DB::table('bitacora_auditoria')->where('accion', 'consulta_auditoria_inventario')->count());
        $this->assertSame([4, 6], DB::table('existencias')->orderBy('id_comercio')->pluck('cantidad_fisica')->map(fn ($v): int => (int) $v)->all());

        $this->refreshApplication();
        config(['integraciones.m4.nucleo.modo' => 'real']);
        app(ContextoComercio::class)->establecer(1);
        try {
            app(InventarioModuleInterface::class)->consultarInventarioAuditoria([1, 2]);
            $this->fail('Un usuario de comercio debía recibir ERR-002.');
        } catch (ErrorInventarioException $e) {
            $this->assertSame(['ERR-002', 403], [$e->codigo, $e->http]);
        }
    }
}
