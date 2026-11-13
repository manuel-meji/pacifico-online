<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos;

use App\Events\M6_Pagos\FacturaGenerada;
use App\Events\M6_Pagos\PagoConfirmado;
use App\Events\M6_Pagos\ReembolsoProcesado;
use App\Services\M6_Pagos\Facturacion\GeneradorXmlComprobante;
use App\Services\M6_Pagos\IdentificadorEventoM6;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

/**
 * Conexión M6–M7 (contrato M6 v1.4.0).
 *
 * - id_evento estable por operación: si M6 vuelve a publicar la misma operación,
 *   M7 recibe el mismo id_evento y su idempotencia no duplica el asiento.
 * - ReembolsoProcesado trae id_pago y FacturaGenerada trae id_pago y el desglose.
 * - Los campos nuevos son opcionales: el payload anterior se mantiene.
 *
 * Pedido 8942 (dobles): 1001 → comercio 11: 30 000 + 3 900 + 1 500 = 35 400.
 */
class EventosConexionM7Test extends TestCase
{
    use PreparaM6;

    public function test_pago_confirmado_usa_un_id_evento_estable_por_id_pago(): void
    {
        Event::fake([PagoConfirmado::class]);

        $idPago = $this->procesarPago()->assertCreated()->json('id');

        Event::assertDispatched(PagoConfirmado::class, fn (PagoConfirmado $e) => $e->idPago === $idPago
            && Str::isUuid($e->idEvento)
            && $e->idEvento === IdentificadorEventoM6::para('PagoConfirmado', $idPago)
            && array_keys($e->payload()) === ['id_evento', 'id_pedido', 'id_pago', 'monto', 'fecha']);
    }

    public function test_factura_generada_trae_id_pago_y_desglose_del_monto(): void
    {
        Event::fake([FacturaGenerada::class]);

        $idPago = $this->procesarPago()->assertCreated()->json('id');

        Event::assertDispatched(FacturaGenerada::class, function (FacturaGenerada $e) use ($idPago): bool {
            $p = $e->payload();

            return $e->idSubpedido === 1001
                && $p['id_pago'] === $idPago
                && $p['id_comercio'] === 11
                && abs($p['subtotal'] - 30000.00) < 0.001
                && abs($p['impuesto'] - 3900.00) < 0.001
                && abs($p['envio'] - 1500.00) < 0.001
                && abs(($p['subtotal'] + $p['impuesto'] + $p['envio']) - $p['monto']) < 0.001
                && $e->idEvento === IdentificadorEventoM6::para('FacturaGenerada', $e->idFactura);
        });
    }

    public function test_reembolso_procesado_trae_id_pago_e_id_evento_estable(): void
    {
        Event::fake([ReembolsoProcesado::class]);

        $idPago = $this->procesarPago()->assertCreated()->json('id');
        $this->postJson('/api/m6/v1/pedidos/'.self::PEDIDO_CONFIRMADO.'/reembolsos', [
            'id_subpedido' => 1001, 'monto' => 5000, 'motivo' => 'Producto devuelto',
        ])->assertCreated();

        Event::assertDispatched(ReembolsoProcesado::class, fn (ReembolsoProcesado $e) => $e->payload()['id_pago'] === $idPago
            && $e->idEvento === IdentificadorEventoM6::para('ReembolsoProcesado', $e->idReembolso));
    }

    public function test_el_id_evento_es_determinista_y_distinto_por_evento_y_operacion(): void
    {
        $id = IdentificadorEventoM6::para('PagoConfirmado', 10);

        $this->assertTrue(Str::isUuid($id));
        $this->assertSame($id, IdentificadorEventoM6::para('PagoConfirmado', 10));
        $this->assertNotSame($id, IdentificadorEventoM6::para('PagoConfirmado', 11));
        $this->assertNotSame($id, IdentificadorEventoM6::para('ReembolsoProcesado', 10));
    }

    public function test_un_xml_que_no_es_factura_de_m6_no_da_desglose(): void
    {
        $generador = new GeneradorXmlComprobante;

        $this->assertNull($generador->totalesFactura(''));
        $this->assertNull($generador->totalesFactura('no es xml'));
        $this->assertNull($generador->totalesFactura('<NotaCreditoElectronica><TotalComprobante>5.00</TotalComprobante></NotaCreditoElectronica>'));
    }

    public function test_los_campos_nuevos_son_opcionales_para_no_romper_a_quien_construye_el_evento(): void
    {
        $reembolso = new ReembolsoProcesado('e', 1, 2, 3, 4, 10.0, 5, '2026-11-12T00:00:00-06:00');
        $factura = new FacturaGenerada('e', 1, 2, 3, 4, 10.0, '<xml/>', '2026-11-12T00:00:00-06:00');

        $this->assertNull($reembolso->payload()['id_pago']);
        $this->assertNull($factura->payload()['subtotal']);
    }
}
