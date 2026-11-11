<?php

namespace App\Listeners\M1_Portal;

use App\Services\M1_Portal\Integraciones\CachePortal;
use Illuminate\Support\Facades\Log;

/**
 * Consume los eventos de comercio de M2 (ComercioAprobado, ComercioSuspendido
 * y ComercioReactivado, versión v1) e invalida la caché del comercio. M1 no
 * modifica sus tablas por estos eventos: el comercio aparece o deja de
 * aparecer porque M2 y M3 lo devuelven o no.
 *
 * M2 todavía no publica las clases de sus eventos, así que los métodos
 * reciben el evento como objeto y leen id_comercio de una propiedad
 * idComercio o id_comercio, o de payload()['id_comercio'] (la forma que usan
 * los eventos de M4). Se registran en M1IntegracionesServiceProvider.
 */
class EventosComercioListener
{
    public function __construct(private CachePortal $cache) {}

    /**
     * El comercio pasó a Activo: su micrositio y productos deben aparecer.
     */
    public function alAprobarComercio(object $evento): void
    {
        $this->invalidar($evento, 'ComercioAprobado');
    }

    /**
     * El comercio fue suspendido: deja de mostrarse.
     */
    public function alSuspenderComercio(object $evento): void
    {
        $this->invalidar($evento, 'ComercioSuspendido');
    }

    /**
     * El comercio volvió a Activo: vuelve a mostrarse en la misma posición de
     * destacados, que se conserva en comercios_destacados.
     */
    public function alReactivarComercio(object $evento): void
    {
        $this->invalidar($evento, 'ComercioReactivado');
    }

    private function invalidar(object $evento, string $nombre): void
    {
        $idComercio = $this->idComercio($evento);

        if ($idComercio === null) {
            Log::warning("M1: el evento {$nombre} no trae id_comercio; no se invalidó la caché.");

            return;
        }

        $this->cache->invalidarComercio($idComercio);
    }

    private function idComercio(object $evento): ?int
    {
        $valor = $evento->idComercio
            ?? $evento->id_comercio
            ?? (method_exists($evento, 'payload') ? ($evento->payload()['id_comercio'] ?? null) : null);

        return is_numeric($valor) ? (int) $valor : null;
    }
}
