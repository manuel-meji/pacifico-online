<?php

namespace App\Services\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Events\M4_Inventario\AlertaStockMinimo;
use App\Events\M4_Inventario\EventoInventario;
use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Events\M4_Inventario\ExistenciaRepuesta;
use App\Models\M4_Inventario\Existencia;
use App\Services\M4_Inventario\Repositorios\ExistenciaRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Sujeto del patrón Observer (sección 7.3 del PC3): después de cada cambio de
 * disponibilidad decide qué eventos publicar. Los eventos se entregan tras el commit.
 *
 *  - ExistenciaAgotada / ExistenciaRepuesta: el total de la variación en el comercio
 *    (todas las ubicaciones) cruza 0 (CS-03).
 *  - AlertaStockMinimo: el disponible de la existencia baja hasta su mínimo (RN-M4-004).
 *    Cada alerta queda además en la bitácora del núcleo, que es el historial de
 *    alertas que pide la regla.
 */
class PublicadorDisponibilidad
{
    public function __construct(
        private readonly ExistenciaRepositoryInterface $existencias,
        private readonly Dispatcher $eventos,
        private readonly NucleoDependencia $nucleo,
    ) {}

    public function notificarCambio(Existencia $existencia, int $disponibleAntes, int $totalAntes): void
    {
        $totalDespues = $this->existencias->disponibleTotal($existencia->id_comercio, $existencia->id_variacion);

        if ($totalAntes > 0 && $totalDespues <= 0) {
            $this->publicar(new ExistenciaAgotada($existencia->id_comercio, $existencia->id_producto, $existencia->id_variacion));
        } elseif ($totalAntes <= 0 && $totalDespues > 0) {
            $this->publicar(new ExistenciaRepuesta($existencia->id_comercio, $existencia->id_producto, $existencia->id_variacion, $totalDespues));
        }

        if ($existencia->stock_minimo > 0 && $disponibleAntes > $existencia->stock_minimo && $existencia->bajoMinimo()) {
            $this->alertar($existencia);
        }
    }

    /** Al fijar el mínimo: alerta si el disponible ya está en o bajo el nuevo mínimo. */
    public function alertarSiBajoMinimo(Existencia $existencia): void
    {
        if ($existencia->bajoMinimo()) {
            $this->alertar($existencia);
        }
    }

    private function alertar(Existencia $existencia): void
    {
        $alerta = new AlertaStockMinimo($existencia);
        $this->publicar($alerta);
        $this->nucleo->registrarAuditoria('alerta_stock_minimo', 'existencias', $existencia->id_existencia, [
            'modulo' => 'M4', 'id_evento' => $alerta->idEvento,
        ] + $alerta->payload());
    }

    private function publicar(EventoInventario $evento): void
    {
        $this->eventos->dispatch($evento);
    }
}
