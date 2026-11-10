<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\Nucleo\ContextoAislamiento;
use App\Services\Nucleo\ContextoCliente;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Database\Eloquent\Builder;

/**
 * RN-12 para los documentos de M6 (facturas y notas): un cliente solo ve los
 * suyos y un comercio solo los que emitió. Fuera de su ámbito se responde 404
 * (ERR-003), igual que ProcesamientoPagosService::exigirMismoCliente.
 */
class AmbitoAccesoM6
{
    public function __construct(
        private readonly ContextoCliente $cliente,
        private readonly ContextoComercio $comercio,
    ) {}

    public function exigir(int $idCliente, int $idComercio): void
    {
        if ($this->fueraDeAmbito($this->cliente, $idCliente) || $this->fueraDeAmbito($this->comercio, $idComercio)) {
            throw ErrorNucleoException::recursoNoAccesible();
        }
    }

    /** Aplica el mismo filtro a una consulta de facturas_electronicas. */
    public function filtrar(Builder $consulta): Builder
    {
        if ($this->activo($this->cliente)) {
            $consulta->where('id_cliente', $this->cliente->idActual());
        }

        if ($this->activo($this->comercio)) {
            $consulta->where('id_comercio', $this->comercio->idActual());
        }

        return $consulta;
    }

    private function fueraDeAmbito(ContextoAislamiento $contexto, int $id): bool
    {
        return $this->activo($contexto) && $contexto->idActual() !== $id;
    }

    private function activo(ContextoAislamiento $contexto): bool
    {
        return ! $contexto->esModoSistema() && $contexto->idActual() !== null;
    }
}
