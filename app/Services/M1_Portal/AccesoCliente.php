<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\ClienteNoAccesibleException;
use App\Services\Nucleo\ContextoCliente;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Regla de acceso de las rutas web de clientes (RN-M1-16, RNF-M1-08): cada
 * persona compradora solo ve y edita sus propios datos, y ninguna cuenta de
 * comercio ni de la plataforma puede consultar clientes.
 *
 * Usa el aislamiento del núcleo (tarea 23 del PC4, RN-12, ADR-006): el
 * cliente de la sesión es el que fijó el middleware EstablecerCliente en
 * ContextoCliente. Si la ruta no aplica ese middleware o la cuenta no es de
 * un cliente, el contexto queda vacío y se niega el acceso (falla cerrado).
 * El modo sistema y el acceso auditado del núcleo no abren estos datos.
 *
 * Solo la usan los controladores de M1. La interfaz PHP (M5, M6, núcleo) no
 * pasa por aquí porque no lleva sesión de usuario.
 *
 * Cualquier acceso que no sea a los propios datos responde ERR-003 (404), sin
 * revelar si el cliente pedido existe.
 */
class AccesoCliente
{
    public function __construct(private ContextoCliente $contexto) {}

    /**
     * id_cliente de la persona con sesión.
     *
     * @throws AuthenticationException si no hay sesión (ERR-001, 401).
     * @throws ClienteNoAccesibleException si la petición no tiene ámbito de cliente.
     */
    public function idClienteDeSesion(?Authenticatable $usuario): int
    {
        if ($usuario === null) {
            throw new AuthenticationException;
        }

        return $this->contexto->idActual() ?? throw new ClienteNoAccesibleException;
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
