<?php

namespace Tests\Doubles\M4;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Fixture del evento ProductoPublicado (M3 → M1 y M4) según el catálogo de eventos del Comité
 * (sección 4.3, v1.0.0). M4 lo usa para crear de forma idempotente la existencia inicial de la variación.
 *
 * Supuesto: el contrato propio de M3 v1.2.1 no declara este evento; la carga útil sale del catálogo
 * del Comité y se confirma con SG3. Se reemplaza cuando M3 publique su clase de evento.
 */
final class EventosCatalogoSimulados
{
    public static function productoPublicado(int $idComercio, int $idProducto, int $idVariacion): array
    {
        foreach (['id_comercio' => $idComercio, 'id_producto' => $idProducto, 'id_variacion' => $idVariacion] as $campo => $id) {
            if ($id < 1) {
                throw new InvalidArgumentException("{$campo} debe ser un entero mayor o igual a 1.");
            }
        }

        return [
            'nombre' => 'ProductoPublicado',
            'version' => 'v1.0.0',
            'carga_util' => [
                'id_evento' => Identificadores::uuid(),
                'fecha_evento' => (new DateTimeImmutable)->format(DATE_ATOM),
                'id_comercio' => $idComercio,
                'id_producto' => $idProducto,
                'id_variacion' => $idVariacion,
            ],
        ];
    }

    /** Devuelve el mismo evento (mismo id_evento), como si la cola lo entregara por segunda vez. */
    public static function duplicar(array $evento): array
    {
        return $evento;
    }
}
