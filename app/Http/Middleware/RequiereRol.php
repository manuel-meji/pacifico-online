<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Nucleo\CodigoErrorNucleo;
use App\Models\Nucleo\Rol;
use App\Models\Nucleo\VerificaRol;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequiereRol
{
    use VerificaRol;

    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $usuario = $request->user();

        if (! $usuario) {
            return $this->error(CodigoErrorNucleo::SesionInvalida);
        }

        $permitidos = array_map(fn (string $r) => Rol::from($r), $roles);

        if (! $this->tieneRol($usuario, ...$permitidos)) {
            return $this->error(CodigoErrorNucleo::RolSinPermiso);
        }

        return $next($request);
    }

    // Provisional: cambiar por RespuestaError cuando la Persona 5 la entregue
    private function error(CodigoErrorNucleo $codigo): JsonResponse
    {
        return response()->json(
            ['error' => [
                'codigo'   => $codigo->value,
                'mensaje'  => $codigo->mensaje(),
                'detalles' => null,
            ]],
            $codigo->codigoHttp()
        );
    }
}