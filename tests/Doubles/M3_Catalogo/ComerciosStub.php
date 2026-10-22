<?php

namespace Tests\Doubles\M3_Catalogo;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Services\M1_Portal\Dobles\ErrorProveedorException;

class ComerciosStub implements ComerciosServiceInterface
{
    private array $comercios = [
        34 => [
            'id_comercio' => 34,
            'nombre_comercial' => 'Tienda La Ceiba',
            'estado' => 'Activo',
            'activo' => true,
            'motivo_estado' => null,
        ],
        35 => [
            'id_comercio' => 35,
            'nombre_comercial' => 'Café Nicoyano',
            'estado' => 'Activo',
            'activo' => true,
            'motivo_estado' => null,
        ],
        36 => [
            'id_comercio' => 36,
            'nombre_comercial' => 'Artesanías Guaitil',
            'estado' => 'Activo',
            'activo' => true,
            'motivo_estado' => null,
        ],
        37 => [
            'id_comercio' => 37,
            'nombre_comercial' => 'Comercio suspendido',
            'estado' => 'Suspendido',
            'activo' => false,
            'motivo_estado' => 'Suspensión simulada para pruebas.',
        ],
    ];

    public function obtenerMicrositio(int $comercioId): array
    {
        if ($comercioId < 1) {
            throw ErrorProveedorException::datosInvalidos([
                'id_comercio' => 'Debe ser un identificador positivo.',
            ]);
        }

        if (!isset($this->comercios[$comercioId])) {
            throw ErrorProveedorException::noEncontrado(
                'El comercio no existe.'
            );
        }

        $comercio = $this->comercios[$comercioId];

        if (!$comercio['activo']) {
            throw ErrorProveedorException::noEncontrado(
                'El comercio no está habilitado para mostrar su micrositio.'
            );
        }

        return [
            'comercio_id' => $comercio['id_comercio'],
            'nombre' => $comercio['nombre_comercial'],
            'descripcion' => 'Micrositio de prueba.',
            'estado' => $comercio['estado'],
        ];
    }

    public function registrarComision(
        int $pedidoId,
        float $subtotalPedido
    ): bool {
        return $pedidoId > 0 && $subtotalPedido >= 0;
    }
}
