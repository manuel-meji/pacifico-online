<?php

namespace Tests\Doubles\M1;

use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\Nucleo\Usuario;

/**
 * Doble del núcleo (ServicioIdentidadInterface) para las pruebas de clientes
 * de M1: entrega cuentas con id_usuario consecutivos desde PRIMER_ID sin tocar
 * la tabla usuarios, o lanza el error que se le indique en $error.
 */
class IdentidadDoble implements ServicioIdentidadInterface
{
    public const PRIMER_ID = 700;

    public int $llamadas = 0;

    /** @var array<string, mixed>|null */
    public ?array $datos = null;

    public ?RolUsuario $rol = null;

    public ?ErrorNucleoException $error = null;

    public function crearCuenta(array $datos, RolUsuario|string $rol, ?int $idComercio = null): Usuario
    {
        $this->llamadas++;
        $this->datos = $datos;
        $this->rol = $rol instanceof RolUsuario ? $rol : RolUsuario::from($rol);

        if ($this->error !== null) {
            throw $this->error;
        }

        $usuario = new Usuario;
        $usuario->forceFill(['id_usuario' => self::PRIMER_ID + $this->llamadas - 1]);

        return $usuario;
    }
}
