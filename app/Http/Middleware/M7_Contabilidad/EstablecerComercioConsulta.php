<?php

declare(strict_types=1);

namespace App\Http\Middleware\M7_Contabilidad;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Http\Middleware\EstablecerComercio;
use App\Services\Nucleo\ContextoComercio;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EstablecerComercioConsulta
{
    public function __construct(private readonly EstablecerComercio $establecerComercio) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        $reglas = $usuario->rol === RolUsuario::EncargadoPlataforma
            ? ['required', 'integer', 'min:1']
            : ['nullable', 'integer', 'min:1'];
        $filtros = $request->validate(['id_comercio' => $reglas]);
        $solicitado = $filtros['id_comercio'] ?? null;

        if ($usuario->rol !== RolUsuario::EncargadoPlataforma) {
            if ($solicitado !== null && (int) $solicitado !== (int) $usuario->id_comercio) {
                throw new AuthorizationException('No tiene autorización para consultar otro comercio.');
            }

            return $this->establecerComercio->handle($request, $next);
        }

        $idComercio = (int) $solicitado;
        app(NucleoServiceInterface::class)->registrarAuditoria(
            'consulta_comercio',
            'comercio',
            $idComercio,
            ['modulo' => 'M7', 'motivo' => sprintf('%s %s', $request->method(), $request->path())]
        );
        app(ContextoComercio::class)->establecer($idComercio);

        return $next($request);
    }
}
