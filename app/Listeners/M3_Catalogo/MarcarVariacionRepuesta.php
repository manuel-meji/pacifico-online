<?php

namespace App\Listeners\M3_Catalogo;

/** M4 -> M3. ExistenciaRepuesta: pone disponible = true en la variacion indicada. */
class MarcarVariacionRepuesta extends ActualizaDisponibilidadVariacion
{
    public function handle(object $evento): void
    {
        $this->aplicar($evento, true, 'ExistenciaRepuesta');
    }
}
