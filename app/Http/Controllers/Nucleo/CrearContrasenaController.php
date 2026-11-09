<?php

declare(strict_types=1);

namespace App\Http\Controllers\Nucleo;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Http\Controllers\Controller;
use App\Models\Nucleo\Usuario;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Define la contraseña de una cuenta invitada (Encargado del comercio) con el token del
 * correo de invitación. Usa el broker «invitaciones», cuyo plazo se configura aparte del
 * de recuperación de contraseña. No inicia sesión: la persona entra luego por /login.
 */
class CrearContrasenaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Mismas reglas de contraseña que ServicioIdentidad::crearCuenta (string, mínimo 8),
        // más la confirmación (contrasena_confirmation).
        $datos = $request->validate([
            'token' => ['required', 'string'],
            'correo' => ['required', 'string', 'email', 'max:150'],
            'contrasena' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $estado = Password::broker('invitaciones')->reset(
            [
                // La clave es «correo», no «email»: es la columna de usuarios.
                'correo' => mb_strtolower(trim($datos['correo'])),
                'activo' => true,
                'password' => $datos['contrasena'],
                'token' => $datos['token'],
            ],
            function (Usuario $usuario, string $contrasena): void {
                // El modelo cifra «contrasena» con el cast hashed.
                $usuario->forceFill(['contrasena' => $contrasena])->save();

                event(new PasswordReset($usuario));
            },
        );

        if ($estado !== Password::PASSWORD_RESET) {
            // Token inválido, vencido, ya usado o correo desconocido: el mismo mensaje, para
            // no revelar qué correos tienen cuenta.
            throw ErrorNucleoException::validacionFallida([
                'token' => ['El enlace de invitación no es válido o ya venció.'],
            ]);
        }

        return response()->json([
            'mensaje' => 'Contraseña definida. Ya puede iniciar sesión.',
        ])->header('Cache-Control', 'no-store');
    }
}
