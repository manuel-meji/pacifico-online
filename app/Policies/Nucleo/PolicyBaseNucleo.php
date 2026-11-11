<?php

declare(strict_types=1);

namespace App\Policies\Nucleo;

use App\Models\Nucleo\Usuario;
use App\Models\Nucleo\VerificaRol;

/**
 * Comprobaciones transversales reutilizables por las Policies.
 *
 * Cada módulo mantiene sus propias Policies para las entidades que posee.
 * Esta clase únicamente centraliza comprobaciones comunes de usuario,
 * rol y pertenencia.
 *
 * El aislamiento de consultas por comercio no se implementa aquí.
 * Ese mecanismo corresponde a EstablecerComercio, ContextoComercio,
 * PerteneceAComercio y FiltroComercioScope.
 */
abstract class PolicyBaseNucleo
{
    use VerificaRol;

    protected function usuarioActivo(Usuario $usuario): bool
    {
        return $usuario->activo === true;
    }

    /**
     * Verifica que el usuario sea un Encargado del comercio activo
     * y que pertenezca al comercio indicado.
     *
     * Esta comprobación complementa el aislamiento automático aplicado
     * por el Núcleo; no sustituye el global scope PerteneceAComercio.
     */
    protected function perteneceAlComercio(
        Usuario $usuario,
        int $idComercio
    ): bool {
        return $this->esEncargadoComercio($usuario)
            && $usuario->id_comercio !== null
            && (int) $usuario->id_comercio === $idComercio;
    }
}
