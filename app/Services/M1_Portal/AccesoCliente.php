<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\ClienteNoAccesibleException;
use App\Contracts\M1_Portal\Exceptions\ClienteNoEncontradoException;
use App\Contracts\M1_Portal\PortalPublicoContract;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Regla de acceso de las rutas web de clientes (RN-M1-16, RNF-M1-08): cada
 * persona compradora solo ve y edita sus propios datos, y ninguna cuenta de
 * comercio ni de la plataforma puede consultar clientes.
 *
 * Solo la usan los controladores de M1. La interfaz PHP (M5, M6, núcleo) no
 * pasa por aquí porque no lleva sesión de usuario.
 *
 * Cualquier acceso que no sea a los propios datos responde ERR-003 (404), sin
 * revelar si el cliente pedido existe.
 */
class AccesoCliente
{
    public function __construct(private PortalPublicoContract $portal) {}

    /**
     * id_cliente de la persona con sesión.
     *
     * @throws AuthenticationException si no hay sesión (ERR-001, 401).
     * @throws ClienteNoAccesibleException si la cuenta no es de un cliente.
     */
    public function idClienteDeSesion(?Authenticatable $usuario): int
    {
        if ($usuario === null) {
            throw new AuthenticationException;
        }

        try {
            return $this->portal->obtenerIdClientePorUsuario((int) $usuario->getAuthIdentifier());
        } catch (ClienteNoEncontradoException $excepcion) {
            throw new ClienteNoAccesibleException(anterior: $excepcion);
        }
    }

    /**
     * Permite seguir solo si $idCliente es el cliente de la persona con sesión.
     *
     * @throws AuthenticationException si no hay sesión (ERR-001, 401).
     * @throws ClienteNoAccesibleException en cualquier otro caso (ERR-003, 404).
     */
    public function exigirPropio(?Authenticatable $usuario, int $idCliente): void
    {
        if ($this->idClienteDeSesion($usuario) !== $idCliente) {
            throw new ClienteNoAccesibleException;
        }
    }
}
