<?php

declare(strict_types=1);

namespace App\Services\M2_Comercios\Dobles;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;

/**
 * Base para los dobles de M2 que construyen los módulos consumidores.
 *
 * Declara las 8 operaciones del contrato v1.2.1 con sus firmas exactas. Por
 * defecto cada una responde ERR-006 (dependencia no disponible): el doble solo
 * sobrescribe las operaciones que su módulo consume.
 *
 *     class ComerciosStub extends ComerciosDobleBase
 *     {
 *         public function obtenerComercioActivo(int $idComercio): array { ... }
 *     }
 *
 * Extender esta clase (en lugar de implementar ComerciosServiceInterface) hace
 * que el doble siga funcionando cuando la interfaz pase a las 8 operaciones.
 */
abstract class ComerciosDobleBase implements ComerciosServiceInterface
{
    public function listarComerciosHabilitados(int $pagina = 1, int $porPagina = 20, bool $destacado = false): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function obtenerResumenComerciosLote(array $idsComercio): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function obtenerDatosComercioPublico(int $idComercio): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function obtenerComercioActivo(int $idComercio): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function obtenerSedesComercio(int $idComercio): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function obtenerPoliticasEnvio(int $idComercio): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function obtenerDatosFiscalesComercio(int $idComercio): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function obtenerComisionSubpedido(int $idSubpedido): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    protected function noSimulada(string $operacion): ErrorNucleoException
    {
        return ErrorNucleoException::proveedorNoResponde('M2', "El doble no simula {$operacion}.");
    }
}
