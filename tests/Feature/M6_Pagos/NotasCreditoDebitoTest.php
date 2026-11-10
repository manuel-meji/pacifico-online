<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M6_Pagos\FacturaElectronica;
use App\Services\M6_Pagos\DocumentoAjusteService;
use App\Services\Nucleo\ContextoComercio;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

/**
 * CU-005 Nota de crédito (RN-004) y CU-006 Nota de débito (RN-005).
 * POST /api/m6/v1/facturas/{id_factura}/notas-credito | notas-debito
 *
 * La factura del subpedido 1001 (comercio 11) suma ₡35 400.
 */
class NotasCreditoDebitoTest extends TestCase
{
    use PreparaM6;

    private const TOTAL_FACTURA = 35400.00;

    /** CP-M6-F18 · CU-005: nota de crédito válida, con XML tipo 03 que referencia la factura. */
    public function test_emite_una_nota_de_credito_valida(): void
    {
        $factura = $this->facturaDelComercio11();

        $nota = $this->notaCredito($factura->id_factura_electronica, 5000, 'Producto devuelto')
            ->assertCreated()
            ->assertJsonPath('id_factura', $factura->id_factura_electronica)
            ->assertJsonPath('tipo', 'credito')
            ->assertJsonPath('motivo', 'Producto devuelto')
            ->assertJsonPath('monto', fn ($monto) => (float) $monto === 5000.0)
            ->json();

        $xml = simplexml_load_string($nota['xml']);

        $this->assertSame('NotaCreditoElectronica', $xml->getName());
        $this->assertSame('0010000103'.str_pad((string) $nota['id'], 10, '0', STR_PAD_LEFT), (string) $xml->NumeroConsecutivo);
        $this->assertMatchesRegularExpression('/^506\d{47}$/', (string) $xml->Clave);
        $this->assertSame($factura->claveNumerica(), (string) $xml->InformacionReferencia->Numero);
        $this->assertSame('Producto devuelto', (string) $xml->InformacionReferencia->Razon);
        $this->assertSame('5000.00', (string) $xml->ResumenFactura->TotalComprobante);

        $this->assertDatabaseCount('notas_credito', 1);
        $this->assertDatabaseHas('bitacora_auditoria', ['accion' => 'emitir_nota_credito', 'modulo' => 'M6_Pagos']);
    }

    /** CP-M6-F19 · RN-004: una nota de crédito mayor que la factura → 422 ERR-M6-017. */
    public function test_la_nota_de_credito_no_puede_superar_la_factura(): void
    {
        $factura = $this->facturaDelComercio11();

        $this->notaCredito($factura->id_factura_electronica, self::TOTAL_FACTURA + 0.01)
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-M6-017')
            ->assertJsonPath('error.detalles.motivo', 'NOTA_EXCEDE_MONTO_FACTURA');

        $this->assertDatabaseCount('notas_credito', 0);
    }

    /** CP-M6-F20 · RN-004 (PC3): se valida el acumulado de notas previas + la nueva. */
    public function test_la_nota_de_credito_respeta_el_saldo_acumulado(): void
    {
        $id = $this->facturaDelComercio11()->id_factura_electronica;

        $this->notaCredito($id, 20000)->assertCreated();

        $this->notaCredito($id, 20000)
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-M6-017')
            ->assertJsonPath('error.detalles.disponible', fn ($disponible) => (float) $disponible === 15400.0);

        // Exactamente el saldo disponible sí se acepta; después ya no queda nada.
        $this->notaCredito($id, 15400)->assertCreated();
        $this->notaCredito($id, 0.01)->assertStatus(422);

        $this->assertDatabaseCount('notas_credito', 2);
    }

    /** CP-M6-F21 · Factura inexistente → 404 ERR-M6-016 en ambas notas. */
    public function test_nota_sobre_factura_inexistente_responde_404(): void
    {
        $this->notaCredito(999, 100)->assertNotFound()->assertJsonPath('error.codigo', 'ERR-M6-016');
        $this->notaDebito(999, 100)->assertNotFound()->assertJsonPath('error.codigo', 'ERR-M6-016');
    }

    /** CP-M6-F22 · RN-005: nota de débito sobre factura no aceptada por Hacienda → 409 ERR-M6-018. */
    public function test_nota_de_debito_sobre_factura_no_aceptada_es_conflicto(): void
    {
        config(['m6.hacienda.respuesta' => 'enviada']);
        $factura = $this->facturaDelComercio11();

        $this->notaDebito($factura->id_factura_electronica, 1000)
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M6-018')
            ->assertJsonPath('error.detalles.estado_hacienda', 'enviada');

        $this->assertDatabaseCount('notas_debito', 0);
    }

    /** CP-M6-F23 · CU-006: nota de débito sobre factura aceptada → 201 con XML tipo 02. */
    public function test_nota_de_debito_sobre_factura_aceptada(): void
    {
        $factura = $this->facturaDelComercio11();

        $nota = $this->notaDebito($factura->id_factura_electronica, 2500.50, 'Ajuste por envío adicional')
            ->assertCreated()
            ->assertJsonPath('tipo', 'debito')
            ->assertJsonPath('monto', fn ($monto) => (float) $monto === 2500.5)
            ->json();

        $xml = simplexml_load_string($nota['xml']);

        $this->assertSame('NotaDebitoElectronica', $xml->getName());
        $this->assertSame('02', substr((string) $xml->NumeroConsecutivo, 8, 2));
        $this->assertSame($factura->claveNumerica(), (string) $xml->InformacionReferencia->Numero);
        $this->assertSame('2500.50', (string) $xml->ResumenFactura->TotalComprobante);

        $this->assertDatabaseCount('notas_debito', 1);
        $this->assertDatabaseCount('notas_credito', 0);
    }

    /** CP-M6-F24 · Validación del cuerpo: monto > 0 con dos decimales y motivo obligatorio (ERR-005). */
    public function test_valida_monto_y_motivo(): void
    {
        $id = $this->facturaDelComercio11()->id_factura_electronica;

        foreach ([0, -10, 10.123, 'abc'] as $monto) {
            $this->notaCredito($id, $monto)
                ->assertStatus(422)
                ->assertJsonPath('error.codigo', 'ERR-005')
                ->assertJsonValidationErrors(['monto'], 'error.detalles');
        }

        $this->postJson("/api/m6/v1/facturas/{$id}/notas-debito", ['monto' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo'], 'error.detalles');
    }

    /** CP-M6-F25 · RN-12: un comercio no emite notas sobre la factura de otro (404, no 403). */
    public function test_un_comercio_no_emite_notas_sobre_facturas_ajenas(): void
    {
        $id = $this->facturaDelComercio11()->id_factura_electronica;

        app(ContextoComercio::class)->establecer(12);

        try {
            app(DocumentoAjusteService::class)->emitirNotaCredito($id, 100, 'Intento ajeno');
            $this->fail('Debió responder RECURSO_NO_ACCESIBLE.');
        } catch (ErrorNucleoException $e) {
            $this->assertSame(404, $e->getCodigoHttp());
        }

        $this->assertDatabaseCount('notas_credito', 0);
    }

    private function facturaDelComercio11(): FacturaElectronica
    {
        $this->procesarPago()->assertCreated();

        return FacturaElectronica::query()->where('id_subpedido', 1001)->firstOrFail();
    }

    private function notaCredito(int $idFactura, mixed $monto, string $motivo = 'Devolución parcial')
    {
        return $this->postJson("/api/m6/v1/facturas/{$idFactura}/notas-credito", ['monto' => $monto, 'motivo' => $motivo]);
    }

    private function notaDebito(int $idFactura, mixed $monto, string $motivo = 'Cargo adicional')
    {
        return $this->postJson("/api/m6/v1/facturas/{$idFactura}/notas-debito", ['monto' => $monto, 'motivo' => $motivo]);
    }
}
