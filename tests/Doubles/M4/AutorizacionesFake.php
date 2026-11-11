<?php

namespace Tests\Doubles\M4;

use App\Contracts\M4_Inventario\Dependencias\VerificadorAutorizacionesInterface;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use DateTimeImmutable;

/**
 * Fake del verificador de autorizaciones del núcleo (supuesto, ver VerificadorAutorizacionesInterface).
 * Valida existencia, vigencia, comercio, operación, variación, ubicaciones y cantidad, y no permite
 * usar dos veces la misma autorización. Cualquier falla responde ERR-002 (403) con el motivo en el
 * mensaje y el identificador en detalles.id_autorizacion.
 */
class AutorizacionesFake implements VerificadorAutorizacionesInterface
{
    /** @var array<string, true> */
    private array $usadas = [];

    public function verificar(string $idAutorizacion, string $operacion, int $idComercio, int $idVariacion, array $idsUbicacion, int $cantidad): array
    {
        $autorizacion = DatosPruebaM4::autorizaciones()[$idAutorizacion] ?? null;

        $motivo = match (true) {
            $autorizacion === null => 'La autorización no existe.',
            isset($this->usadas[$idAutorizacion]) => 'La autorización ya fue utilizada.',
            new DateTimeImmutable > new DateTimeImmutable($autorizacion['vence']) => 'La autorización venció.',
            $autorizacion['id_comercio'] !== $idComercio => 'La autorización es de otro comercio.',
            $autorizacion['operacion'] !== $operacion,
            $autorizacion['id_variacion'] !== $idVariacion,
            $autorizacion['ubicaciones'] !== array_values($idsUbicacion),
            $autorizacion['cantidad'] !== $cantidad => 'La autorización no corresponde a la operación solicitada.',
            default => null,
        };

        if ($motivo !== null) {
            throw new ErrorInventarioException('ERR-002', 403, $motivo, ['id_autorizacion' => $idAutorizacion]);
        }

        return [
            'id_autorizacion' => $idAutorizacion,
            'id_autorizador' => $autorizacion['id_autorizador'],
            'fecha_autorizacion' => $autorizacion['fecha_autorizacion'],
        ];
    }

    public function marcarUsada(string $idAutorizacion): void
    {
        $this->usadas[$idAutorizacion] = true;
    }
}
