<?php

namespace App\Contracts\M1_Portal\Exceptions;

/**
 * La persona con sesión pide datos de un cliente que no es ella (RN-M1-16),
 * o su cuenta no pertenece a ningún cliente (ERR-003, 404).
 *
 * Igual que el aislamiento del núcleo, responde 404 y no revela si ese
 * cliente existe. Solo se lanza en las rutas web; la interfaz PHP que usan
 * M5, M6 y el núcleo no tiene sesión de usuario.
 */
class ClienteNoAccesibleException extends PortalPublicoException
{
    protected string $codigo = 'ERR-003';

    protected function mensajePorDefecto(): string
    {
        return 'El cliente solicitado no existe o no es accesible.';
    }
}
