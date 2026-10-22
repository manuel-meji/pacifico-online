<?php

namespace Tests\Doubles\M2;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;

class MockComerciosService implements ComerciosServiceInterface
{
    public function obtenerDatosFiscalesComercio(int $idComercio): array
    {
        return [
            'id_comercio' => $idComercio,
            'razon_social' => 'Tienda Demostración S.A.',
            'cedula_juridica' => '3101888999',
            'porcentaje_comision' => 5.00,
            'correo_fiscal' => 'facturacion@comerciodemo.cr',
        ];
    }
}
