<?php

declare(strict_types=1);

namespace App\Services\Nucleo;

use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\Nucleo\Usuario;
use App\Notifications\Nucleo\InvitacionEncargadoComercio;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Creación de cuentas del núcleo (PC3, sección 5.1).
 *
 * - Cliente: se autorregistra; M1 conduce el flujo y llama aquí dentro de su transacción.
 * - Encargado del comercio: lo solicita M2 al aprobar un comercio; recibe una invitación por
 *   correo para definir su contraseña.
 * - Encargado de la plataforma: sin autorregistro; lo crea el comando de instalación o un
 *   encargado ya existente.
 *
 * Esta clase no autoriza a quien la llama: cada módulo decide quién puede pedir una cuenta.
 */
class ServicioIdentidad implements ServicioIdentidadInterface
{
    public function crearCuenta(array $datos, RolUsuario|string $rol, ?int $idComercio = null): Usuario
    {
        $rolEnum = $this->resolverRol($rol);

        $campos = [
            'nombre' => isset($datos['nombre']) ? trim((string) $datos['nombre']) : null,
            'correo' => isset($datos['correo']) ? mb_strtolower(trim((string) $datos['correo'])) : null,
            'contrasena' => $datos['contrasena'] ?? null,
            'id_comercio' => $idComercio,
        ];

        $validador = Validator::make($campos, $this->reglas($rolEnum));

        if ($validador->fails()) {
            throw ErrorNucleoException::validacionFallida($validador->errors()->toArray());
        }

        // El Encargado del comercio no trae contraseña: se genera una aleatoria que nadie
        // conoce, y la persona define la suya con la invitación.
        $contrasena = $rolEnum === RolUsuario::EncargadoComercio
            ? Str::random(64)
            : $campos['contrasena'];

        try {
            $usuario = Usuario::create([
                'nombre' => $campos['nombre'],
                'correo' => $campos['correo'],
                'contrasena' => $contrasena,
                'rol' => $rolEnum,
                'id_comercio' => $rolEnum === RolUsuario::EncargadoComercio ? $idComercio : null,
                'activo' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dos solicitudes simultáneas con el mismo correo: la restricción de la base de datos decide.
            throw ErrorNucleoException::validacionFallida([
                'correo' => ['El correo ya está registrado.'],
            ]);
        }

        if ($rolEnum === RolUsuario::EncargadoComercio) {
            $token = Password::broker()->createToken($usuario);
            $usuario->notify(new InvitacionEncargadoComercio($token));
        }

        return $usuario;
    }

    public function actualizarCorreo(int $idUsuario, string $correo): void
    {
        $usuario = Usuario::query()->find($idUsuario)
            ?? throw ErrorNucleoException::recursoNoEncontrado();

        $nuevo = mb_strtolower(trim($correo));

        $validador = Validator::make(['correo' => $nuevo], [
            'correo' => [
                'required',
                'email',
                'max:150',
                Rule::unique('usuarios', 'correo')->ignore($idUsuario, 'id_usuario'),
            ],
        ]);

        if ($validador->fails()) {
            throw ErrorNucleoException::validacionFallida($validador->errors()->toArray());
        }

        try {
            $usuario->correo = $nuevo;
            $usuario->save();
        } catch (UniqueConstraintViolationException) {
            // Dos solicitudes simultáneas con el mismo correo: la restricción de la base de datos decide.
            throw ErrorNucleoException::validacionFallida([
                'correo' => ['El correo ya está registrado.'],
            ]);
        }
    }

    private function resolverRol(RolUsuario|string $rol): RolUsuario
    {
        if ($rol instanceof RolUsuario) {
            return $rol;
        }

        return RolUsuario::tryFrom($rol)
            ?? throw ErrorNucleoException::validacionFallida([
                'rol' => ['El rol no es válido.'],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(RolUsuario $rol): array
    {
        $esEncargadoComercio = $rol === RolUsuario::EncargadoComercio;

        return [
            'nombre' => ['required', 'string', 'max:150'],
            'correo' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'correo')],
            'contrasena' => $esEncargadoComercio
                ? ['nullable']
                : ['required', 'string', 'min:8'],
            // id_comercio es obligatorio solo para el Encargado del comercio y nulo en los demás (5.2).
            'id_comercio' => $esEncargadoComercio
                ? ['required', 'integer', 'min:1']
                : ['prohibited'],
        ];
    }
}
