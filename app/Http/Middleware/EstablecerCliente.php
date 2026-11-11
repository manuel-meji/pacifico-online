<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Nucleo\ResuelveClienteInterface;
use App\Services\Nucleo\ContextoCliente;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstablecerCliente
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Si M1 aún no entrega la implementación, no hay ámbito de cliente
        // y los scopes no devuelven registros (falla cerrado).
        if ($usuario !== null && app()->bound(ResuelveClienteInterface::class)) {
            $idCliente = app(ResuelveClienteInterface::class)
                ->obtenerIdClientePorUsuario((int) $usuario->id_usuario);

            if ($idCliente !== null) {
                app(ContextoCliente::class)->establecer($idCliente);
            }
        }

        return $next($request);
    }
}
