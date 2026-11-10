<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos\Facturacion;

use DateTimeInterface;
use DOMDocument;
use DOMElement;

/**
 * Arma el XML de los comprobantes electrónicos de M6 (CU-004, CU-005, CU-006).
 *
 * Es una versión simplificada del formato de Hacienda (decisión abierta 3):
 * conserva las piezas que se validan en el curso (clave de 50 dígitos,
 * consecutivo de 20, emisor, receptor, totales y referencia a la factura
 * en las notas), sin firma digital.
 *
 * Consecutivo (20): sucursal (3) + terminal (5) + tipo de documento (2) + número (10).
 * Clave (50): país 506 (3) + fecha ddmmaa (6) + cédula del emisor (12)
 *             + consecutivo (20) + situación (1) + código de seguridad (8).
 */
class GeneradorXmlComprobante
{
    public const TIPO_FACTURA = '01';

    public const TIPO_NOTA_DEBITO = '02';

    public const TIPO_NOTA_CREDITO = '03';

    private const SUCURSAL = '001';

    private const TERMINAL = '00001';

    private const SITUACION_NORMAL = '1';

    public function consecutivo(string $tipoDocumento, int $numero): string
    {
        return self::SUCURSAL.self::TERMINAL.$tipoDocumento.str_pad((string) $numero, 10, '0', STR_PAD_LEFT);
    }

    public function clave(string $cedulaEmisor, string $consecutivo, DateTimeInterface $fecha): string
    {
        $cedula = str_pad(substr(preg_replace('/\D/', '', $cedulaEmisor) ?? '', -12), 12, '0', STR_PAD_LEFT);
        $seguridad = str_pad((string) (crc32($consecutivo.$cedula.$fecha->format('U')) % 100_000_000), 8, '0', STR_PAD_LEFT);

        return '506'.$fecha->format('dmy').$cedula.$consecutivo.self::SITUACION_NORMAL.$seguridad;
    }

    /**
     * @param  array{clave: string, consecutivo: string, fecha: DateTimeInterface, emisor: array{nombre: string, cedula: string, condicion: string}, receptor: array{nombre: ?string, identificacion: ?string}, subtotal: float, impuesto: float, envio: float, total: float}  $datos
     */
    public function factura(array $datos): string
    {
        [$xml, $raiz] = $this->documento('FacturaElectronica', $datos['clave'], $datos['consecutivo'], $datos['fecha']);

        $this->emisor($xml, $raiz, $datos['emisor']);
        $this->receptor($xml, $raiz, $datos['receptor']);

        $resumen = $this->hijo($xml, $raiz, 'ResumenFactura');
        $this->hijo($xml, $resumen, 'CodigoMoneda', 'CRC');
        $this->hijo($xml, $resumen, 'TotalVenta', $this->monto($datos['subtotal']));
        $this->hijo($xml, $resumen, 'TotalImpuesto', $this->monto($datos['impuesto']));
        $this->hijo($xml, $resumen, 'TotalOtrosCargos', $this->monto($datos['envio']));
        $this->hijo($xml, $resumen, 'TotalComprobante', $this->monto($datos['total']));

        return (string) $xml->saveXML();
    }

    /**
     * Nota de crédito (03) o de débito (02) que referencia a una factura.
     *
     * @param  array{clave: string, consecutivo: string, fecha: DateTimeInterface, emisor: array{nombre: string, cedula: string, condicion: string}, receptor: array{nombre: ?string, identificacion: ?string}, factura: array{clave: string, fecha: DateTimeInterface}, monto: float, motivo: string}  $datos
     */
    public function nota(string $tipoDocumento, array $datos): string
    {
        $raizNombre = $tipoDocumento === self::TIPO_NOTA_CREDITO ? 'NotaCreditoElectronica' : 'NotaDebitoElectronica';

        [$xml, $raiz] = $this->documento($raizNombre, $datos['clave'], $datos['consecutivo'], $datos['fecha']);

        $this->emisor($xml, $raiz, $datos['emisor']);
        $this->receptor($xml, $raiz, $datos['receptor']);

        $resumen = $this->hijo($xml, $raiz, 'ResumenFactura');
        $this->hijo($xml, $resumen, 'CodigoMoneda', 'CRC');
        $this->hijo($xml, $resumen, 'TotalComprobante', $this->monto($datos['monto']));

        $referencia = $this->hijo($xml, $raiz, 'InformacionReferencia');
        $this->hijo($xml, $referencia, 'TipoDoc', self::TIPO_FACTURA);
        $this->hijo($xml, $referencia, 'Numero', $datos['factura']['clave']);
        $this->hijo($xml, $referencia, 'FechaEmision', $datos['factura']['fecha']->format(DATE_ATOM));
        // 01 = anula el documento de referencia; 02 = corrige un monto. Las notas de M6 corrigen montos.
        $this->hijo($xml, $referencia, 'Codigo', '02');
        $this->hijo($xml, $referencia, 'Razon', $datos['motivo']);

        return (string) $xml->saveXML();
    }

    /** @return array{0: DOMDocument, 1: DOMElement} */
    private function documento(string $raizNombre, string $clave, string $consecutivo, DateTimeInterface $fecha): array
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $raiz = $xml->createElement($raizNombre);
        $xml->appendChild($raiz);

        $this->hijo($xml, $raiz, 'Clave', $clave);
        $this->hijo($xml, $raiz, 'NumeroConsecutivo', $consecutivo);
        $this->hijo($xml, $raiz, 'FechaEmision', $fecha->format(DATE_ATOM));

        return [$xml, $raiz];
    }

    /** @param array{nombre: string, cedula: string, condicion: string} $emisor */
    private function emisor(DOMDocument $xml, DOMElement $raiz, array $emisor): void
    {
        $nodo = $this->hijo($xml, $raiz, 'Emisor');
        $this->hijo($xml, $nodo, 'Nombre', $emisor['nombre']);
        $identificacion = $this->hijo($xml, $nodo, 'Identificacion');
        $this->hijo($xml, $identificacion, 'Tipo', '02'); // 02 = cédula jurídica
        $this->hijo($xml, $identificacion, 'Numero', $emisor['cedula']);
        $this->hijo($xml, $nodo, 'CondicionContribuyente', $emisor['condicion']);
    }

    /** @param array{nombre: ?string, identificacion: ?string} $receptor */
    private function receptor(DOMDocument $xml, DOMElement $raiz, array $receptor): void
    {
        $nodo = $this->hijo($xml, $raiz, 'Receptor');

        if ($receptor['nombre'] !== null && $receptor['nombre'] !== '') {
            $this->hijo($xml, $nodo, 'Nombre', $receptor['nombre']);
        }

        // M1 todavía no publica la identificación tributaria (decisión abierta 6): se omite si viene vacía.
        if ($receptor['identificacion'] !== null && $receptor['identificacion'] !== '') {
            $identificacion = $this->hijo($xml, $nodo, 'Identificacion');
            $this->hijo($xml, $identificacion, 'Numero', $receptor['identificacion']);
        }
    }

    private function hijo(DOMDocument $xml, DOMElement $padre, string $nombre, ?string $texto = null): DOMElement
    {
        $nodo = $xml->createElement($nombre);

        if ($texto !== null) {
            // createTextNode escapa &, < y > (razones sociales con "&", motivos libres).
            $nodo->appendChild($xml->createTextNode($texto));
        }

        $padre->appendChild($nodo);

        return $nodo;
    }

    private function monto(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }
}
