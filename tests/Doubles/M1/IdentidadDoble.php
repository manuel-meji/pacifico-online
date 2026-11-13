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

    public int $llamadasActualizarCorreo = 0;

    /** @var list<array{id_usuario: int, correo: string}> */
    public array $correosActualizados = [];

    public ?ErrorNucleoException $errorActualizarCorreo = null;

    public int $llamadasExisteCorreo = 0;

    /** @var list<string> */
    public array $correosExistentes = [];

    public function crearCuenta(array $datos, RolUsuario|string $rol, ?int $idComercio = null): Usuario
    {
        $this->llamadas++;
        $this->datos = $datos;
        $this->rol = $rol instanceof RolUsuario ? $rol : RolUsuario::from($rol);

        if ($this->error !== null) {
            throw $this->error;
        }

        if (isset($datos['correo'])) {
            $this->correosExistentes[] = mb_strtolower(trim((string) $datos['correo']));
        }

        $usuario = new Usuario;
        $usuario->forceFill(['id_usuario' => self::PRIMER_ID + $this->llamadas - 1]);

        return $usuario;
    }

    public function actualizarCorreo(int $idUsuario, string $correo): void
    {
        $this->llamadasActualizarCorreo++;

        if ($this->errorActualizarCorreo !== null) {
            throw $this->errorActualizarCorreo;
        }

        $this->correosActualizados[] = ['id_usuario' => $idUsuario, 'correo' => $correo];
        $this->correosExistentes[] = mb_strtolower(trim($correo));
    }

    public function existeCorreo(string $correo): bool
    {
        $this->llamadasExisteCorreo++;

        $correoNormalizado = mb_strtolower(trim($correo));

        if ($correoNormalizado === '' || ! filter_var($correoNormalizado, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $normalizados = array_map(fn (string $c): string => mb_strtolower(trim($c)), $this->correosExistentes);

        if (in_array($correoNormalizado, $normalizados, true)) {
            return true;
        }

        return isset($this->datos['correo'])
            && mb_strtolower(trim((string) $this->datos['correo'])) === $correoNormalizado;
    }
}
