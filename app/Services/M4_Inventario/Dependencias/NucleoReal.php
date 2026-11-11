<?php

namespace App\Services\M4_Inventario\Dependencias;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\Nucleo\ContextoComercio;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Núcleo real, llamado dentro del mismo proceso.
 *
 * El aislamiento RN-12 no es un filtro de M4: lo aplica el Global Scope del núcleo
 * (PerteneceAComercio) con el comercio que el middleware EstablecerComercio deja en
 * ContextoComercio. Aquí solo se decide el ámbito de cada operación:
 *  - sesión de comercio o acceso auditado del Encargado de plataforma: se respeta tal cual;
 *  - sin sesión de comercio: modo sistema del núcleo (llamadas internas de otros módulos);
 *  - consultas públicas del contrato (disponibilidad): modo sistema siempre.
 */
class NucleoReal implements NucleoDependencia
{
    public function __construct(private readonly NucleoServiceInterface $nucleo) {}

    public function comercioActual(): ?int
    {
        return $this->nucleo->getComercioActualId();
    }

    public function usuarioActual(): int
    {
        return (int) (Auth::id() ?? 0);
    }

    public function ejecutarEnAmbito(Closure $operacion, bool $publica = false): mixed
    {
        $contexto = app(ContextoComercio::class);

        try {
            if (! $publica && ($contexto->idActual() !== null || $contexto->esModoSistema())) {
                return $operacion();
            }

            return $contexto->ejecutarComoSistema($operacion);
        } catch (ErrorNucleoException) {
            // Escritura fuera del ámbito del comercio de la sesión (VerificaAmbitoEscritura).
            throw ErrorInventarioException::recursoNoAccesible();
        }
    }

    public function registrarAuditoria(string $accion, string $entidad, int $entidadId, array $detalles = []): void
    {
        $this->nucleo->registrarAuditoria($accion, $entidad, $entidadId, $detalles);
    }
}
