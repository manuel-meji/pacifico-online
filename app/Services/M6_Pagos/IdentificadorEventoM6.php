<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use Ramsey\Uuid\Uuid;

/**
 * id_evento estable de los eventos que publica M6 (conexión M6–M7).
 *
 * El id_evento es un UUID v5 calculado con el nombre del evento y el id de la
 * operación (id_pago, id_reembolso, id_factura). Así, si M6 vuelve a publicar
 * la misma operación, sale con el mismo id_evento y la idempotencia de los
 * consumidores (M7: eventos_procesados) la descarta en vez de duplicar asientos.
 *
 * El formato sigue siendo un UUID, por lo que el contrato no cambia.
 */
final class IdentificadorEventoM6
{
    /** Espacio de nombres fijo de M6. No cambiarlo: cambiaría todos los id_evento. */
    private const ESPACIO = '6f1c2a0e-3b7d-5e9a-8c41-4d2f0b6a9e17';

    public static function para(string $evento, int $idOperacion): string
    {
        return Uuid::uuid5(self::ESPACIO, "pacificos-online:m6:{$evento}:{$idOperacion}")->toString();
    }
}
