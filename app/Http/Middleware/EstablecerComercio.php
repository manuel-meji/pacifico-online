<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Nucleo\ContextoComercio;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstablecerComercio
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Solo el Encargado del comercio tiene id_comercio. Si no lo tiene,
        // no se establece ámbito y los scopes no devuelven registros.
        // El comercio nunca se toma de un encabezado HTTP.
        if ($usuario?->activo && $usuario->id_comercio !== null) {
            app(ContextoComercio::class)->establecer((int) $usuario->id_comercio);
        }

        return $next($request);
    }
}