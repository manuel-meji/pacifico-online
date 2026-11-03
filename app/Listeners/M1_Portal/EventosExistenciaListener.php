<?php

namespace App\Listeners\M1_Portal;

use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Events\M4_Inventario\ExistenciaRepuesta;
use App\Services\M1_Portal\Integraciones\CachePortal;

/**
 * Consume los eventos de existencias de M4 (ExistenciaAgotada y
 * ExistenciaRepuesta, versión 1.0.0) y marca o quita el Agotado de la
 * variación en la caché del portal. Se registran en M1IntegracionesServiceProvider.
 */
class EventosExistenciaListener
{
    public function __construct(private CachePortal $cache) {}

    public function alAgotarExistencia(ExistenciaAgotada $evento): void
    {
        $this->cache->marcarAgotado($evento->idProducto, $evento->idVariacion);
    }

    public function alReponerExistencia(ExistenciaRepuesta $evento): void
    {
        $this->cache->quitarAgotado($evento->idProducto, $evento->idVariacion);
    }
}
