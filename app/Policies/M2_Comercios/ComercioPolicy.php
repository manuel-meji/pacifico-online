<?php

declare(strict_types=1);

namespace App\Policies\M2_Comercios;

use App\Enums\Nucleo\RolUsuario;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M2_Comercios\Comercio;
use App\Models\Nucleo\Usuario;
use App\Models\Nucleo\VerificaRol;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Autorización HTTP de las consultas de M2 (x-autorizacion del contrato v1.2.1).
 *
 * Orden de las verificaciones: rol (403, ERR-002) y luego ámbito del comercio
 * (404, ERR-003, para no revelar si el comercio existe).
 *
 * Nota: compara con RolUsuario (el enum que castea Usuario) en lugar de usar el
 * trait VerificaRol del núcleo, que hoy falla con un Usuario real. Recibe
 * Authenticatable porque el guard web todavía apunta a App\Models\User; un
 * usuario sin rol recibe 403. Cuando el núcleo lo corrija, se cambia por
 * VerificaRol y Usuario.
 */
class ComercioPolicy
{
    use VerificaRol;

    // --- CU-0001 a CU-0007: edición del micrositio
    public function actualizar(Usuario $usuario, Comercio $comercio): bool
    {
        return $this->esEncargadoComercio($usuario)
            && $usuario->id_comercio === $comercio->id_comercio;
    }

    /**
     * Estado, sedes y datos fiscales: plataforma, cualquier comercio;
     * encargado del comercio, solo el suyo; cliente, 403.
     */
    /** CU-0017 (diseño): solo el Encargado de la plataforma suspende comercios. */
    public function suspender(Usuario $usuario, Comercio $comercio): bool
    {
        return $this->esEncargadoPlataforma($usuario);
    }

    /** CU-0018 (diseño): solo el Encargado de la plataforma reactiva comercios. */
    public function reactivar(Usuario $usuario, Comercio $comercio): bool
    {
        return $this->esEncargadoPlataforma($usuario);
    }

    /** Listado y ficha administrativa de comercios (panel de la plataforma). */
    public function administrar(Usuario $usuario, Comercio $comercio): bool
    {
        return $this->esEncargadoPlataforma($usuario);
    }

    public function consultarComercio(Authenticatable $usuario, int $idComercio): void
    {
        $rol = $this->rolActivo($usuario);

        if ($rol === RolUsuario::EncargadoPlataforma) {
            return;
        }
        if ($rol === RolUsuario::EncargadoComercio) {
            $this->exigirPropio($usuario, $idComercio);

            return;
        }

        throw ErrorNucleoException::rolSinPermiso();
    }

    /**
     * Políticas de envío: además de los anteriores, el cliente puede consultar
     * cualquier comercio habilitado (la verificación del estado la hace el controlador).
     */
    public function consultarPoliticasEnvio(Authenticatable $usuario, int $idComercio): void
    {
        if ($this->rolActivo($usuario) === RolUsuario::Cliente) {
            return;
        }

        $this->consultarComercio($usuario, $idComercio);
    }

    /** Comisión de un subpedido: plataforma, cualquiera; encargado, solo de su comercio. */
    public function consultarComision(Authenticatable $usuario, int $idComercioDelSubpedido): void
    {
        $this->consultarComercio($usuario, $idComercioDelSubpedido);
    }

    public function esCliente(Authenticatable $usuario): bool
    {
        return $this->rolActivo($usuario) === RolUsuario::Cliente;
    }

    private function rolActivo(Authenticatable $usuario): ?RolUsuario
    {
        if (! ($usuario->activo ?? false)) {
            return null;
        }
        $rol = $usuario->rol ?? null;

        return $rol instanceof RolUsuario ? $rol : RolUsuario::tryFrom((string) $rol);
    }

    private function exigirPropio(Authenticatable $usuario, int $idComercio): void
    {
        $propio = $usuario->id_comercio ?? null;
        if ($propio === null || (int) $propio !== $idComercio) {
            throw ErrorNucleoException::recursoNoAccesible();
        }
    }
}
