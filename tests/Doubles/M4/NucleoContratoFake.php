<?php

namespace Tests\Doubles\M4;

use App\Contracts\Nucleo\NucleoServiceInterface;

/**
 * Doble que imita la interfaz del núcleo publicada en el repositorio (NucleoServiceInterface),
 * para probar el adaptador real de M4 (NucleoReal): comercio de la sesión configurable y
 * bitácora en memoria que registra lo que M4 le envía.
 *
 * registrarAuditoria acepta int|string en entidad_id porque las reservas usan UUID; M4 pide
 * al Comité que la bitácora admita texto (sección 1.1.1).
 */
class NucleoContratoFake implements NucleoServiceInterface
{
    /** @var list<array{accion: string, entidad: string, entidad_id: int|string, detalles: array<string, mixed>}> */
    public array $bitacora = [];

    public function __construct(private ?int $idComercio = 104) {}

    public function getComercioActualId(): ?int
    {
        return $this->idComercio;
    }

    public function registrarAuditoria(string $accion, string $entidad, int|string $entidadId, array $detalles = []): void
    {
        $this->bitacora[] = ['accion' => $accion, 'entidad' => $entidad, 'entidad_id' => $entidadId, 'detalles' => $detalles];
    }
}
