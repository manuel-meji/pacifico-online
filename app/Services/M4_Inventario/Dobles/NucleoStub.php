<?php

namespace App\Services\M4_Inventario\Dobles;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;

/**
 * Doble por defecto del núcleo para M4: sesión del comercio 1 y bitácora en
 * memoria, para que una prueba pueda revisar qué se registró.
 * Se sustituye en config/integraciones.php, clave m4.nucleo.doble.
 */
class NucleoStub implements NucleoDependencia
{
    /** @var list<array{accion: string, entidad: string, entidad_id: int, detalles: array<string, mixed>}> */
    public array $bitacora = [];

    public function __construct(private ?int $comercio = 1) {}

    public function comercioActual(): ?int
    {
        return $this->comercio;
    }

    public function registrarAuditoria(string $accion, string $entidad, int $entidadId, array $detalles = []): void
    {
        $this->bitacora[] = ['accion' => $accion, 'entidad' => $entidad, 'entidad_id' => $entidadId, 'detalles' => $detalles];
    }
}
