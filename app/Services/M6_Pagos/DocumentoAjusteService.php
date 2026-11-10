<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Models\M6_Pagos\FacturaElectronica;
use App\Models\M6_Pagos\NotaCredito;
use App\Models\M6_Pagos\NotaDebito;
use App\Services\M6_Pagos\Facturacion\GeneradorXmlComprobante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * CU-005 Nota de crédito (RN-004) y CU-006 Nota de débito (RN-005).
 *
 * - RN-004: el acumulado de notas de crédito (previas + la nueva) no puede
 *   superar el total de la factura. La fila de la factura se bloquea mientras
 *   se suma, así dos notas simultáneas no pasan juntas el límite. En PostgreSQL
 *   el trigger trg_validar_monto_nota_credito es la segunda barrera.
 * - RN-005: la nota de débito solo se emite sobre una factura aceptada por Hacienda.
 * - Cada nota genera su propio XML (tipo 03 crédito / 02 débito) que referencia
 *   la clave de la factura.
 *
 * emitirNotaCredito() también la usará el reembolso (CU-007, paquete C); puede
 * llamarse dentro de una transacción abierta.
 */
class DocumentoAjusteService
{
    public function __construct(
        private readonly GeneradorXmlComprobante $generador,
        private readonly AmbitoAccesoM6 $ambito,
        private readonly NucleoServiceInterface $nucleo,
    ) {}

    /** @throws ErrorContratoException FACTURA_NO_ENCONTRADA | NOTA_EXCEDE_MONTO_FACTURA */
    public function emitirNotaCredito(int $idFactura, float $monto, string $motivo): NotaCredito
    {
        $this->validarDatos($monto, $motivo);

        $nota = DB::transaction(function () use ($idFactura, $monto, $motivo): NotaCredito {
            $factura = $this->facturaBloqueada($idFactura);

            $totalCentimos = $this->centimos((float) $factura->monto_total);
            $acumuladoCentimos = $this->centimos((float) $factura->notasCredito()->sum('monto'));

            if ($acumuladoCentimos + $this->centimos($monto) > $totalCentimos) {
                throw ErrorContratoException::de(CodigoErrorM6::NotaExcedeMontoFactura, null, [
                    'monto_factura' => $totalCentimos / 100,
                    'acreditado' => $acumuladoCentimos / 100,
                    'disponible' => ($totalCentimos - $acumuladoCentimos) / 100,
                ]);
            }

            return $this->crear(new NotaCredito, GeneradorXmlComprobante::TIPO_NOTA_CREDITO, $factura, $monto, $motivo);
        });

        $this->auditar('emitir_nota_credito', 'notas_credito', $nota->id_nota_credito, $nota);

        return $nota;
    }

    /** @throws ErrorContratoException FACTURA_NO_ENCONTRADA | FACTURA_NO_ACEPTADA */
    public function emitirNotaDebito(int $idFactura, float $monto, string $motivo): NotaDebito
    {
        $this->validarDatos($monto, $motivo);

        $nota = DB::transaction(function () use ($idFactura, $monto, $motivo): NotaDebito {
            $factura = $this->facturaBloqueada($idFactura);

            if (! $factura->estaAceptada()) {
                throw ErrorContratoException::de(CodigoErrorM6::FacturaNoAceptada, null, ['estado_hacienda' => $factura->codigoEstado()]);
            }

            return $this->crear(new NotaDebito, GeneradorXmlComprobante::TIPO_NOTA_DEBITO, $factura, $monto, $motivo);
        });

        $this->auditar('emitir_nota_debito', 'notas_debito', $nota->id_nota_debito, $nota);

        return $nota;
    }

    private function facturaBloqueada(int $idFactura): FacturaElectronica
    {
        $factura = FacturaElectronica::query()->with('estado')->lockForUpdate()->find($idFactura)
            ?? throw ErrorContratoException::de(CodigoErrorM6::FacturaNoEncontrada);

        $this->ambito->exigir($factura->id_cliente, $factura->id_comercio);

        return $factura;
    }

    /**
     * El consecutivo de la nota usa su propio id, así que primero se inserta la
     * fila y luego se completa el XML (todo dentro de la misma transacción).
     *
     * @template T of NotaCredito|NotaDebito
     *
     * @param  T  $nota
     * @return T
     */
    private function crear(NotaCredito|NotaDebito $nota, string $tipoDocumento, FacturaElectronica $factura, float $monto, string $motivo): NotaCredito|NotaDebito
    {
        $fecha = now();

        $nota->fill([
            'id_factura_electronica' => $factura->id_factura_electronica,
            'monto' => round($monto, 2),
            'motivo' => $motivo,
            'xml_documento' => '<pendiente/>',
            'fecha_emision' => $fecha,
        ])->save();

        $consecutivo = $this->generador->consecutivo($tipoDocumento, (int) $nota->getKey());

        $nota->forceFill(['xml_documento' => $this->generador->nota($tipoDocumento, [
            'clave' => $this->generador->clave($factura->cedula_juridica_emisor, $consecutivo, $fecha),
            'consecutivo' => $consecutivo,
            'fecha' => $fecha,
            'emisor' => [
                'nombre' => $factura->razon_social_emisor,
                'cedula' => $factura->cedula_juridica_emisor,
                'condicion' => $this->condicionDe($factura),
            ],
            'receptor' => ['nombre' => null, 'identificacion' => $factura->identificacion_receptor],
            'factura' => ['clave' => (string) $factura->claveNumerica(), 'fecha' => $factura->fecha_emision ?? $fecha],
            'monto' => round($monto, 2),
            'motivo' => $motivo,
        ])])->save();

        return $nota;
    }

    /** La condición del emisor se copia de la factura original (no se vuelve a consultar a M2). */
    private function condicionDe(FacturaElectronica $factura): string
    {
        return preg_match('/<CondicionContribuyente>([^<]+)<\/CondicionContribuyente>/', $factura->xml_documento, $m) === 1
            ? $m[1]
            : 'inscrito';
    }

    private function validarDatos(float $monto, string $motivo): void
    {
        if ($this->centimos($monto) <= 0 || trim($motivo) === '') {
            throw new InvalidArgumentException('La nota necesita un monto mayor que cero y un motivo.');
        }
    }

    /** Se compara en céntimos para no arrastrar errores de punto flotante. */
    private function centimos(float $monto): int
    {
        return (int) round($monto * 100);
    }

    private function auditar(string $accion, string $tabla, int $id, NotaCredito|NotaDebito $nota): void
    {
        try {
            $this->nucleo->registrarAuditoria($accion, $tabla, $id, [
                'modulo' => 'M6_Pagos',
                'id_factura' => $nota->id_factura_electronica,
                'monto' => (float) $nota->monto,
                'motivo' => $nota->motivo,
            ]);
        } catch (Throwable $e) {
            Log::warning("[M6_Pagos] No se pudo registrar la auditoría de {$tabla} {$id}: {$e->getMessage()}");
        }
    }
}
