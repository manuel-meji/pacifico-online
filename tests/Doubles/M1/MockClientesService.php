<?php

namespace Tests\Doubles\M1;

use App\Contracts\M1_Portal\ClientesServiceInterface;

class MockClientesService implements ClientesServiceInterface
{
    public function obtenerCliente(int $idCliente): array
    {
        return [
            'id_cliente' => $idCliente,
            'nombre' => 'Juan',
            'apellidos' => 'Pérez González',
            'identificacion_tributaria' => '118230456',
            'tipo_identificacion' => '01',
            'correo_electronico' => 'juan.perez@example.com',
        ];
    }
}
