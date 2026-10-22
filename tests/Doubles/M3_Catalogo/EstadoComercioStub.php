<?php

namespace Tests\Doubles\M3_Catalogo;

class EstadoComercioStub
{
    private array $comercios = [
        1 => [
            'id_comercio' => 1,
            'estado' => 'Activo',
            'activo' => true,
            'motivo_estado' => null,
        ],
        37 => [
            'id_comercio' => 37,
            'estado' => 'Suspendido',
            'activo' => false,
            'motivo_estado' => 'Suspensión simulada para pruebas.',
        ],
    ];

    public function obtenerEstado(int $comercioId): array
    {
        if (!isset($this->comercios[$comercioId])) {
            throw ErrorProveedorException::noEncontrado(
                'El comercio no existe.'
            );
        }

        return $this->comercios[$comercioId];
    }

    public function aprobar(int $comercioId): array
    {
        $this->comercios[$comercioId] = [
            'id_comercio' => $comercioId,
            'estado' => 'Activo',
            'activo' => true,
            'motivo_estado' => null,
        ];

        return $this->crearEvento(
            'ComercioAprobado',
            $comercioId,
            ['nombre_comercial' => 'Comercio de prueba']
        );
    }

    public function suspender(
        int $comercioId,
        string $motivo
    ): array {
        $this->comercios[$comercioId] = [
            'id_comercio' => $comercioId,
            'estado' => 'Suspendido',
            'activo' => false,
            'motivo_estado' => $motivo,
        ];

        return $this->crearEvento(
            'ComercioSuspendido',
            $comercioId,
            ['motivo' => $motivo]
        );
    }

    public function reactivar(int $comercioId): array
    {
        $this->comercios[$comercioId] = [
            'id_comercio' => $comercioId,
            'estado' => 'Activo',
            'activo' => true,
            'motivo_estado' => null,
        ];

        return $this->crearEvento(
            'ComercioReactivado',
            $comercioId,
            ['nombre_comercial' => 'Comercio de prueba']
        );
    }

    private function crearEvento(
        string $tipo,
        int $comercioId,
        array $datos
    ): array {
        return array_merge([
            'tipo_evento' => $tipo,
            'id_evento' => $this->generarUuid(),
            'fecha_hora' => date(DATE_ATOM),
            'id_comercio' => $comercioId,
            'estado' => $tipo === 'ComercioSuspendido'
                ? 'Suspendido'
                : 'Activo',
        ], $datos);
    }

    private function generarUuid(): string
    {
        $datos = random_bytes(16);
        $datos[6] = chr((ord($datos[6]) & 0x0f) | 0x40);
        $datos[8] = chr((ord($datos[8]) & 0x3f) | 0x80);

        $hex = bin2hex($datos);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
