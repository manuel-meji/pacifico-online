<?php

namespace App\Listeners\M3_Catalogo;

/** M4 -> M3. ExistenciaAgotada: pone disponible = false en la variacion indicada. */
class MarcarVariacionAgotada extends ActualizaDisponibilidadVariacion
{
    public function handle(object $evento): void
    {
        $this->aplicar($evento, false, 'ExistenciaAgotada');
    }
}
