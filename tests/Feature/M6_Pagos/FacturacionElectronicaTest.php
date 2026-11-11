<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos;

use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Events\M6_Pagos\FacturaGenerada;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M6_Pagos\FacturaElectronica;
use App\Services\M6_Pagos\Clients\ComercioClient;
use App\Services\M6_Pagos\Clients\DatosFiscalesDTO;
use App\Services\M6_Pagos\FacturacionService;
use App\Services\Nucleo\ContextoCliente;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Doubles\M2\FakeComercioClient;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

/**
 * CU-004 Emitir factura electrónica (RF-004, RN-003) — GET /api/m6/v1/facturas.
 *
 * Datos de los dobles: el pedido 8942 (cliente 77) tiene dos subpedidos,
 *   1001 → comercio 11: 30 000 + 3 900 + 1 500 = 35 400
 *   1002 → comercio 12: 21 000 + 2 730 +   710 = 24 440
 */
class FacturacionElectronicaTest extends TestCase
{
    use PreparaM6;

    /** CP-M6-F06 · CU-004 · decisión 3.1: al confirmar el pago se emite una factura por subpedido. */
    public function test_confirmar_el_pago_emite_una_factura_por_subpedido(): void
    {
        Event::fake([FacturaGenerada::class]);

        $this->procesarPago()->assertCreated()->assertJsonPath('estado', 'confirmado');

        $facturas = $this->getJson('/api/m6/v1/facturas?id_pedido='.self::PEDIDO_CONFIRMADO)
            ->assertOk()
            ->assertJsonCount(2)
            ->json();

        $this->assertSame([1001, 1002], array_column($facturas, 'id_subpedido'));
        $this->assertSame([11, 12], array_column($facturas, 'id_comercio'));
        $this->assertEqualsWithDelta(35400.00, $facturas[0]['monto_total'], 0.001);
        $this->assertEqualsWithDelta(24440.00, $facturas[1]['monto_total'], 0.001);
        $this->assertSame(['aceptada', 'aceptada'], array_column($facturas, 'estado_hacienda'));
        $this->assertSame([self::PEDIDO_CONFIRMADO, self::PEDIDO_CONFIRMADO], array_column($facturas, 'id_pedido'));

        Event::assertDispatchedTimes(FacturaGenerada::class, 2);
        Event::assertDispatched(FacturaGenerada::class, fn (FacturaGenerada $e) => $e->idSubpedido === 1001
            && abs($e->monto - 35400.00) < 0.001
            && str_contains($e->xml, '<FacturaElectronica>'));
    }

    /** CP-M6-F07 · Idempotencia: repetir la confirmación o pedir la factura otra vez no duplica nada. */
    public function test_repetir_la_confirmacion_no_duplica_facturas(): void
    {
        Event::fake([FacturaGenerada::class]);

        $pago = $this->procesarPago()->assertCreated();

        // La pasarela reenvía el mismo webhook.
        $this->webhook($pago->json('id'), ['referencia_proveedor' => $pago->json('referencia_proveedor'), 'estado' => 'aprobado'])
            ->assertOk();

        // M5 pide la factura por la fachada (dos veces).
        $primera = app(PagosServiceInterface::class)->generarFactura(self::PEDIDO_CONFIRMADO);
        $segunda = app(PagosServiceInterface::class)->generarFactura(self::PEDIDO_CONFIRMADO);

        $this->assertDatabaseCount('facturas_electronicas', 2);
        Event::assertDispatchedTimes(FacturaGenerada::class, 2);
        $this->assertSame($primera, $segunda);
    }

    /** CP-M6-F08 · Contrato M5-M6: generarFactura conserva las claves de la respuesta provisional. */
    public function test_la_fachada_generar_factura_conserva_las_claves_del_contrato(): void
    {
        $this->procesarPago()->assertCreated();

        $respuesta = app(PagosServiceInterface::class)->generarFactura(self::PEDIDO_CONFIRMADO);

        $this->assertSame('00100001010000001001', $respuesta['numero_factura']);
        $this->assertMatchesRegularExpression('/^506\d{47}$/', $respuesta['clave_numerica']);
        $this->assertSame('aceptada', $respuesta['estado']);
        $this->assertCount(2, $respuesta['facturas']);
    }

    /** CP-M6-F09 · RN-003: sin pago confirmado no se factura (ERR-M6-014, 409). */
    public function test_sin_pago_confirmado_no_se_factura(): void
    {
        $this->procesarPago(['token_medio_pago' => 'tok_sim_rechazada'])->assertJsonPath('estado', 'rechazado');

        try {
            app(PagosServiceInterface::class)->generarFactura(self::PEDIDO_CONFIRMADO);
            $this->fail('Debió lanzar PAGO_NO_CONFIRMADO.');
        } catch (ErrorContratoException $e) {
            $this->assertSame('ERR-M6-014', $e->getCodigo());
            $this->assertSame(409, $e->getCodigoHttp());
        }

        $this->assertDatabaseCount('facturas_electronicas', 0);
    }

    /**
     * CP-M6-F10 · CU-004 flujo alterno: datos fiscales incompletos (ERR-M6-013).
     * La factura de ese comercio no se emite, el pago no se deshace y la del otro comercio sí sale.
     */
    public function test_datos_fiscales_incompletos_no_deshacen_el_pago(): void
    {
        $this->comercioConFallo(12, new ErrorContratoException('DATOS_FISCALES_INCOMPLETOS'));

        $this->procesarPago()->assertCreated()->assertJsonPath('estado', 'confirmado');

        $this->assertDatabaseCount('facturas_electronicas', 1);
        $this->assertDatabaseHas('facturas_electronicas', ['id_subpedido' => 1001]);

        try {
            app(PagosServiceInterface::class)->generarFactura(self::PEDIDO_CONFIRMADO);
            $this->fail('Debió lanzar DATOS_FISCALES_INCOMPLETOS.');
        } catch (ErrorContratoException $e) {
            $this->assertSame('ERR-M6-013', $e->getCodigo());
            $this->assertSame(422, $e->getCodigoHttp());
        }

        $this->getJson('/api/m6/v1/pagos/1')->assertOk()->assertJsonPath('estado', 'confirmado');
    }

    /** CP-M6-F11 · CU-004 flujo alterno: M2 no responde → el cobro sigue y la factura se reintenta después. */
    public function test_si_m2_no_responde_la_factura_queda_pendiente_y_se_reintenta(): void
    {
        $this->comercioConFallo(null, new RuntimeException('M2 no responde'));

        $this->procesarPago()->assertCreated()->assertJsonPath('estado', 'confirmado');
        $this->assertDatabaseCount('facturas_electronicas', 0);

        // M2 vuelve: el reintento emite las dos facturas.
        $this->app->instance(ComercioClient::class, new FakeComercioClient);

        $respuesta = app(PagosServiceInterface::class)->generarFactura(self::PEDIDO_CONFIRMADO);

        $this->assertCount(2, $respuesta['facturas']);
        $this->assertDatabaseCount('facturas_electronicas', 2);
    }

    /** CP-M6-F12 · Hacienda no disponible: la factura queda "pendiente" y el reintento la envía. */
    public function test_si_hacienda_no_responde_la_factura_queda_pendiente_de_envio(): void
    {
        config(['m6.hacienda.respuesta' => 'no_disponible']);

        $this->procesarPago()->assertCreated();

        $id = FacturaElectronica::query()->value('id_factura_electronica');
        $this->getJson("/api/m6/v1/facturas/{$id}")->assertOk()->assertJsonPath('estado_hacienda', 'pendiente');
        $this->assertSame('pendiente_envio', FacturaElectronica::query()->find($id)->load('estado')->codigoEstado());

        config(['m6.hacienda.respuesta' => 'aceptada']);
        app(PagosServiceInterface::class)->generarFactura(self::PEDIDO_CONFIRMADO);

        $this->getJson("/api/m6/v1/facturas/{$id}")->assertOk()->assertJsonPath('estado_hacienda', 'aceptada');
    }

    /** CP-M6-F13 · Hacienda rechaza el comprobante. */
    public function test_la_factura_rechazada_por_hacienda_se_publica_como_rechazada(): void
    {
        config(['m6.hacienda.respuesta' => 'rechazada']);

        $this->procesarPago()->assertCreated();

        $this->getJson('/api/m6/v1/facturas?id_pedido='.self::PEDIDO_CONFIRMADO)
            ->assertOk()
            ->assertJsonPath('0.estado_hacienda', 'rechazada')
            ->assertJsonPath('1.estado_hacienda', 'rechazada');
    }

    /** CP-M6-F14 · El XML trae emisor, receptor, consecutivo, clave y monto. */
    public function test_el_xml_contiene_emisor_receptor_consecutivo_y_monto(): void
    {
        $this->procesarPago()->assertCreated();

        $id = FacturaElectronica::query()->where('id_subpedido', 1001)->value('id_factura_electronica');
        $factura = $this->getJson("/api/m6/v1/facturas/{$id}")
            ->assertOk()
            ->assertJsonPath('id', $id)
            ->assertJsonPath('id_pedido', self::PEDIDO_CONFIRMADO)
            ->assertJsonPath('consecutivo', '00100001010000001001')
            ->json();

        $xml = simplexml_load_string($factura['xml']);

        $this->assertSame('00100001010000001001', (string) $xml->NumeroConsecutivo);
        $this->assertSame($factura['clave_numerica'], (string) $xml->Clave);
        $this->assertSame('Comercio Pacífico Sur S.A.', (string) $xml->Emisor->Nombre);
        $this->assertSame('3101123456', (string) $xml->Emisor->Identificacion->Numero);
        $this->assertSame('Fulano Castillo', (string) $xml->Receptor->Nombre);
        $this->assertSame('116540892', (string) $xml->Receptor->Identificacion->Numero);
        $this->assertSame('35400.00', (string) $xml->ResumenFactura->TotalComprobante);
        $this->assertSame('3900.00', (string) $xml->ResumenFactura->TotalImpuesto);

        $this->assertDatabaseHas('facturas_electronicas', [
            'id_factura_electronica' => $id,
            'razon_social_emisor' => 'Comercio Pacífico Sur S.A.',
            'cedula_juridica_emisor' => '3101123456',
            'identificacion_receptor' => '116540892',
        ]);
    }

    /** CP-M6-F15 · Factura inexistente → 404 ERR-M6-016. */
    public function test_factura_inexistente_responde_404(): void
    {
        $this->getJson('/api/m6/v1/facturas/999')
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-M6-016')
            ->assertJsonPath('error.detalles.motivo', 'FACTURA_NO_ENCONTRADA');
    }

    /** CP-M6-F16 · Listar: pedido sin facturas devuelve lista vacía; sin id_pedido es ERR-005. */
    public function test_listar_facturas_valida_el_pedido_y_devuelve_lista_vacia(): void
    {
        $this->getJson('/api/m6/v1/facturas?id_pedido=123')->assertOk()->assertExactJson([]);

        $this->getJson('/api/m6/v1/facturas')
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005');
    }

    /** CP-M6-F17 · RN-12: ni otro cliente ni otro comercio ven la factura (404, no 403). */
    public function test_un_cliente_o_comercio_ajeno_no_ve_la_factura(): void
    {
        $this->procesarPago()->assertCreated();
        $id = FacturaElectronica::query()->where('id_comercio', 11)->value('id_factura_electronica');
        $facturacion = app(FacturacionService::class);

        app(ContextoComercio::class)->establecer(12);
        $this->assertNoAccesible(fn () => $facturacion->obtener($id));
        $this->assertSame([1002], $facturacion->listarPorPedido(self::PEDIDO_CONFIRMADO)->pluck('id_subpedido')->all());

        app(ContextoComercio::class)->establecer(11);
        $this->assertSame($id, $facturacion->obtener($id)->id_factura_electronica);

        app(ContextoCliente::class)->establecer(78);
        $this->assertNoAccesible(fn () => $facturacion->obtener($id));
        $this->assertCount(0, $facturacion->listarPorPedido(self::PEDIDO_CONFIRMADO));
    }

    /** Reemplaza el doble de M2: el comercio indicado (o todos, con null) falla con $error. */
    private function comercioConFallo(?int $idComercio, \Throwable $error): void
    {
        $this->app->instance(ComercioClient::class, new class($idComercio, $error) implements ComercioClient
        {
            public function __construct(private readonly ?int $idComercio, private readonly \Throwable $error) {}

            public function obtenerDatosFiscales(int $idComercio): DatosFiscalesDTO
            {
                if ($this->idComercio === null || $this->idComercio === $idComercio) {
                    throw $this->error;
                }

                return (new FakeComercioClient)->obtenerDatosFiscales($idComercio);
            }
        });
    }

    private function assertNoAccesible(\Closure $consulta): void
    {
        try {
            $consulta();
            $this->fail('Debió responder RECURSO_NO_ACCESIBLE.');
        } catch (ErrorNucleoException $e) {
            $this->assertSame(404, $e->getCodigoHttp());
        }
    }
}
