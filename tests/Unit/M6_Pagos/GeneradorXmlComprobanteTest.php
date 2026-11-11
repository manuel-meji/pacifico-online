<?php

declare(strict_types=1);

namespace Tests\Unit\M6_Pagos;

use App\Services\M6_Pagos\Facturacion\GeneradorXmlComprobante;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** CU-004 / CU-005 / CU-006: formato de consecutivo, clave y XML de los comprobantes. */
class GeneradorXmlComprobanteTest extends TestCase
{
    private GeneradorXmlComprobante $generador;

    protected function setUp(): void
    {
        $this->generador = new GeneradorXmlComprobante;
    }

    /** CP-M6-U12 · Consecutivo de 20 dígitos: sucursal + terminal + tipo + número. */
    public function test_el_consecutivo_tiene_20_digitos_y_el_tipo_de_documento(): void
    {
        $this->assertSame('00100001010000001001', $this->generador->consecutivo(GeneradorXmlComprobante::TIPO_FACTURA, 1001));
        $this->assertSame('00100001030000000007', $this->generador->consecutivo(GeneradorXmlComprobante::TIPO_NOTA_CREDITO, 7));
        $this->assertSame('00100001020000000007', $this->generador->consecutivo(GeneradorXmlComprobante::TIPO_NOTA_DEBITO, 7));
    }

    /** CP-M6-U13 · Clave numérica de 50 dígitos con país, fecha, cédula y consecutivo. */
    public function test_la_clave_tiene_50_digitos(): void
    {
        $consecutivo = $this->generador->consecutivo(GeneradorXmlComprobante::TIPO_FACTURA, 1);
        $clave = $this->generador->clave('3-101-123456', $consecutivo, new DateTimeImmutable('2026-11-10 10:00:00'));

        $this->assertMatchesRegularExpression('/^\d{50}$/', $clave);
        $this->assertStringStartsWith('506101126', $clave);
        $this->assertSame('003101123456', substr($clave, 9, 12));
        $this->assertSame($consecutivo, substr($clave, 21, 20));
    }

    /** CP-M6-U14 · El XML escapa caracteres especiales y omite la identificación del receptor si falta. */
    public function test_el_xml_escapa_textos_y_omite_receptor_sin_identificacion(): void
    {
        $xml = $this->generador->factura([
            'clave' => str_repeat('1', 50),
            'consecutivo' => '00100001010000000001',
            'fecha' => new DateTimeImmutable('2026-11-10 10:00:00'),
            'emisor' => ['nombre' => 'Pérez & Hijos <S.A.>', 'cedula' => '3101000001', 'condicion' => 'no_inscrito'],
            'receptor' => ['nombre' => 'Ana', 'identificacion' => null],
            'subtotal' => 1000,
            'impuesto' => 130,
            'envio' => 0,
            'total' => 1130,
        ]);

        $documento = simplexml_load_string($xml);

        $this->assertNotFalse($documento);
        $this->assertSame('Pérez & Hijos <S.A.>', (string) $documento->Emisor->Nombre);
        $this->assertSame('no_inscrito', (string) $documento->Emisor->CondicionContribuyente);
        $this->assertSame('Ana', (string) $documento->Receptor->Nombre);
        $this->assertCount(0, $documento->Receptor->Identificacion);
        $this->assertSame('1130.00', (string) $documento->ResumenFactura->TotalComprobante);
    }
}
