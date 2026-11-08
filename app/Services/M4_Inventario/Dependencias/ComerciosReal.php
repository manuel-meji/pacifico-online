<?php

namespace App\Services\M4_Inventario\Dependencias;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Throwable;

/**
 * M2 real, llamado dentro del mismo proceso por su interfaz PHP (sección 5.1
 * del PC3). Mientras M2 no publique ObtenerComercioActivo u ObtenerSedesComercio
 * en ComerciosServiceInterface, la llamada responde ERR-006 con el motivo.
 */
class ComerciosReal implements ComerciosDependencia
{
    public function __construct(private readonly ComerciosServiceInterface $comercios) {}

    public function obtenerComercioActivo(int $idComercio): array
    {
        return $this->llamar('obtenerComercioActivo', $idComercio);
    }

    public function obtenerSedesComercio(int $idComercio): array
    {
        return $this->llamar('obtenerSedesComercio', $idComercio);
    }

    /**
     * @return array<string, mixed>
     */
    private function llamar(string $operacion, int $idComercio): array
    {
        if (! method_exists($this->comercios, $operacion)) {
            throw ErrorInventarioException::dependenciaNoDisponible('M2', "M2 aún no publica {$operacion} en ComerciosServiceInterface.");
        }

        try {
            return $this->comercios->{$operacion}($idComercio);
        } catch (ErrorInventarioException $e) {
            throw $e;
        } catch (Throwable) {
            throw ErrorInventarioException::dependenciaNoDisponible('M2');
        }
    }
}
