<?php

namespace Tests\Doubles\M1;

use App\Services\M1_Portal\Dobles\ComerciosStub;

/**
 * M2 entrega solo los campos obligatorios de ComercioPublico: los opcionales
 * vienen en null o no vienen (CU-009 A3).
 */
class ComercioConDatosMinimos extends ComerciosStub
{
    public function obtenerDatosComercioPublico(int $idComercio): array
    {
        $comercio = parent::obtenerDatosComercioPublico($idComercio);

        return [
            'id_comercio' => $comercio['id_comercio'],
            'nombre_comercial' => $comercio['nombre_comercial'],
            'estado' => $comercio['estado'],
            'descripcion' => null,
            'logo_url' => null,
            'contacto' => null,
            'banners' => null,
        ];
    }
}
