<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Services\Nucleo\ContextoComercio;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstablecerComercio
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario?->activo !== true) {
            return $next($request);
        }

        // Solo el Encargado del comercio tiene id_comercio. Si no lo tiene,
        // no se establece ámbito y los scopes no devuelven registros.
        // El comercio nunca se toma de un encabezado HTTP.
        if ($usuario->id_comercio !== null) {
            app(ContextoComercio::class)->establecer((int) $usuario->id_comercio);

            return $next($request);
        }

        // Permiso especial auditado del Encargado de la plataforma (PC3, sección
        // 5.3): puede operar sin el filtro de comercio, pero cada concesión
        // queda registrada en la bitácora de auditoría.
        if ($usuario->rol === RolUsuario::EncargadoPlataforma) {
            app(ContextoComercio::class)->concederAccesoAuditado();

            app(NucleoServiceInterface::class)->registrarAuditoria(
                'acceso_total_comercios',
                'contexto_comercio',
                (int) $usuario->id_usuario,
                [
                    'modulo' => 'NUCLEO',
                    'motivo' => sprintf('Encargado de plataforma: %s %s', $request->method(), $request->path()),
                ]
            );
        }

        return $next($request);
    }
}
