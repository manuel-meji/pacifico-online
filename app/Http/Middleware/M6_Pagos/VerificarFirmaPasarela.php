<?php

declare(strict_types=1);

namespace App\Http\Middleware\M6_Pagos;

use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticidad del webhook de la pasarela (contrato M6: 401 NOTIFICACION_NO_AUTENTICA).
 *
 * La pasarela firma el cuerpo crudo con HMAC-SHA256 y un secreto compartido:
 *   X-Firma-Pasarela: hash_hmac('sha256', <cuerpo>, M6_WEBHOOK_SECRETO)
 */
class VerificarFirmaPasarela
{
    public function handle(Request $request, Closure $next): Response
    {
        $secreto = (string) config('m6.webhook.secreto');
        $firma = (string) $request->header(config('m6.webhook.cabecera_firma', 'X-Firma-Pasarela'), '');
        $esperada = hash_hmac('sha256', $request->getContent(), $secreto);

        if ($secreto === '' || $firma === '' || ! hash_equals($esperada, $firma)) {
            throw ErrorContratoException::de(CodigoErrorM6::NotificacionNoAutentica);
        }

        return $next($request);
    }
}
