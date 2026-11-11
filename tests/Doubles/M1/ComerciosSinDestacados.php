<?php

namespace Tests\Doubles\M1;

use App\Services\M1_Portal\Dobles\ComerciosStub;

/**
 * M2 responde, pero ningún comercio tiene una publicidad vigente aprobada:
 * listarComerciosHabilitados con destacado=true devuelve una lista vacía.
 */
class ComerciosSinDestacados extends ComerciosStub
{
    public function listarComerciosHabilitados(int $pagina = 1, int $porPagina = 20, bool $destacado = false): array
    {
        $respuesta = parent::listarComerciosHabilitados($pagina, $porPagina, $destacado);

        if ($destacado) {
            $respuesta['comercios'] = [];
            $respuesta['total'] = 0;
        }

        return $respuesta;
    }
}
