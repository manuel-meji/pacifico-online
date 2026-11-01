<?php

namespace Tests\Doubles\M7;

use App\Services\M6_Pagos\Clients\ContabilidadClient;

/** Fake de M7. Con $disponible = false simula que M7 no responde (conciliación "parcial"). */
final class FakeContabilidadClient implements ContabilidadClient
{
    public array $eventos = [];

    public function __construct(private bool $disponible = true) {}

    public function enviarEventoFinanciero(int $idPago, string $tipo): void
    {
        $this->eventos[] = [$idPago, $tipo];
    }

    public function consultarRegistroContablePago(int $idPago): bool
    {
        if (! $this->disponible) {
            throw new \RuntimeException('M7 no responde');
        }
        return in_array($idPago, array_column($this->eventos, 0), true);
    }
}
