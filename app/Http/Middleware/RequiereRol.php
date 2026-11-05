<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Nucleo\CodigoErrorNucleo;
use App\Enums\Nucleo\RolUsuario;
use App\Models\Nucleo\VerificaRol;
use App\Services\Nucleo\FormateadorErrores;
use Closure;
use Illuminate\Http\Request;

class RequiereRol
{
    use VerificaRol;

    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $usuario = $request->user();

        if (! $usuario) {
            return FormateadorErrores::respuestaDesdeCodigo(CodigoErrorNucleo::SesionInvalida);
        }

        $permitidos = array_map(fn (string $r) => RolUsuario::from($r), $roles);

        if (! $this->tieneRol($usuario, ...$permitidos)) {
            return FormateadorErrores::respuestaDesdeCodigo(CodigoErrorNucleo::RolSinPermiso);
        }

        return $next($request);
    }
}