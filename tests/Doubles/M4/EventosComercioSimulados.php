<?php

namespace Tests\Doubles\M4;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Fixtures de los eventos que M2 publica y M4 consume (contrato m2-comercios v1.2.1,
 * x-eventos-publicados, versión v1): ComercioAprobado, ComercioSuspendido y ComercioReactivado.
 * M4 los usa para bloquear o desbloquear los movimientos manuales del comercio (RN-M4-009).
 *
 * Cada evento es ['nombre' => ..., 'version' => 'v1', 'carga_util' => [...]]. Para probar la
 * idempotencia del listener se reenvía el mismo arreglo (mismo id_evento) con duplicar().
 * Se reemplazan cuando M2 publique sus clases de evento en la cola.
 */
final class EventosComercioSimulados
{
    public static function comercioAprobado(int $idComercio, string $nombreComercial = 'Comercio de prueba'): array
    {
        return self::armar('ComercioAprobado', [
            'id_evento' => Identificadores::uuid(),
            'fecha_hora' => self::ahora(),
            'id_comercio' => self::validarId($idComercio),
            'estado' => 'Activo',
            'nombre_comercial' => $nombreComercial,
        ]);
    }

    /** El motivo es obligatorio y no puede ir vacío (contrato de M2). */
    public static function comercioSuspendido(int $idComercio, string $motivo = 'Incumplimiento de políticas'): array
    {
        if (trim($motivo) === '') {
            throw new InvalidArgumentException('El motivo de la suspensión es obligatorio y no puede ir vacío.');
        }

        return self::armar('ComercioSuspendido', [
            'id_evento' => Identificadores::uuid(),
            'fecha_hora' => self::ahora(),
            'id_comercio' => self::validarId($idComercio),
            'estado' => 'Suspendido',
            'motivo' => $motivo,
        ]);
    }

    public static function comercioReactivado(int $idComercio, string $nombreComercial = 'Comercio de prueba'): array
    {
        return self::armar('ComercioReactivado', [
            'id_evento' => Identificadores::uuid(),
            'fecha_hora' => self::ahora(),
            'id_comercio' => self::validarId($idComercio),
            'estado' => 'Activo',
            'nombre_comercial' => $nombreComercial,
        ]);
    }

    /** Devuelve el mismo evento (mismo id_evento), como si la cola lo entregara por segunda vez. */
    public static function duplicar(array $evento): array
    {
        return $evento;
    }

    private static function armar(string $nombre, array $cargaUtil): array
    {
        return ['nombre' => $nombre, 'version' => 'v1', 'carga_util' => $cargaUtil];
    }

    private static function ahora(): string
    {
        return (new DateTimeImmutable)->format(DATE_ATOM);
    }

    private static function validarId(int $idComercio): int
    {
        if ($idComercio < 1) {
            throw new InvalidArgumentException('id_comercio debe ser un entero mayor o igual a 1.');
        }

        return $idComercio;
    }
}
