<?php

namespace Tests\Doubles\M3_Catalogo;

class DisponibilidadAgotadaStub
{
    public function crearEvento(
        int $comercioId,
        int $productoId,
        int $variacionId
    ): array {
        return [
            'id_comercio' => $comercioId,
            'id_producto' => $productoId,
            'id_variacion' => $variacionId,
            'id_ubicacion' => null,
            'cantidad_disponible' => 0,
            'fecha_evento' => date(DATE_ATOM),
            'id_evento' => $this->generarUuid(),
            'version_evento' => '1.0.0',
        ];
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
