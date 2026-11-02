<?php

declare(strict_types=1);

namespace App\Services\Nucleo;

use App\Contracts\Nucleo\ErrorEstandarInterface;
use App\Enums\Nucleo\CodigoErrorNucleo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Motor central de gestión, renderizado y registro de errores de la plataforma
 * según las especificaciones de la Sección 5.5 del PC3.
 */
class ManejadorErrores
{
    /**
     * Códigos de estado HTTP esperados (4xx) que NO deben registrarse como
     * errores de sistema en el log diario.
     */
    private const CODIGOS_HTTP_NO_LOG = [401, 403, 404, 422];

    /**
     * Transforma cualquier excepción en una respuesta HTTP JsonResponse con el formato
     * estándar del Núcleo: {"error": {"codigo": ..., "mensaje": ..., "detalles": ...}}.
     */
    public static function renderizar(Throwable $e, Request $request): JsonResponse
    {
        // 1. Excepciones que implementan el contrato estándar del Núcleo
        if ($e instanceof ErrorEstandarInterface) {
            return FormateadorErrores::respuesta(
                $e->getCodigo(),
                $e->getMensaje(),
                $e->getCodigoHttp(),
                $e->getDetalles()
            );
        }

        // 2. Excepciones con método toArray() personalizado (compatibilidad con módulos)
        if (method_exists($e, 'toArray')) {
            $array = $e->toArray();
            if (isset($array['error']['codigo'], $array['error']['mensaje'])) {
                $status = property_exists($e, 'http') && is_int($e->http)
                    ? $e->http
                    : 400;

                return response()->json($array, $status);
            }
        }

        // 3. Validación de datos fallida (Laravel ValidationException -> ERR-005, 422)
        if ($e instanceof ValidationException) {
            return FormateadorErrores::respuesta(
                CodigoErrorNucleo::ValidacionFallida->value,
                CodigoErrorNucleo::ValidacionFallida->mensaje(),
                CodigoErrorNucleo::ValidacionFallida->codigoHttp(),
                $e->errors()
            );
        }

        // 4. Sesión inválida o expirada (Laravel AuthenticationException -> ERR-001, 401)
        if ($e instanceof AuthenticationException) {
            return FormateadorErrores::respuesta(
                CodigoErrorNucleo::SesionInvalida->value,
                CodigoErrorNucleo::SesionInvalida->mensaje(),
                CodigoErrorNucleo::SesionInvalida->codigoHttp(),
                null
            );
        }

        // 5. Sin permisos (AuthorizationException / AccessDeniedHttpException -> ERR-002, 403)
        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return FormateadorErrores::respuesta(
                CodigoErrorNucleo::RolSinPermiso->value,
                CodigoErrorNucleo::RolSinPermiso->mensaje(),
                CodigoErrorNucleo::RolSinPermiso->codigoHttp(),
                null
            );
        }

        // 6. Recurso no encontrado (ModelNotFoundException / NotFoundHttpException -> ERR-004, 404)
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return FormateadorErrores::respuesta(
                CodigoErrorNucleo::RecursoNoEncontrado->value,
                CodigoErrorNucleo::RecursoNoEncontrado->mensaje(),
                CodigoErrorNucleo::RecursoNoEncontrado->codigoHttp(),
                null
            );
        }

        // 7. Otras excepciones HTTP de Symfony/Laravel
        if ($e instanceof HttpExceptionInterface) {
            $statusCode = $e->getStatusCode();

            return match ($statusCode) {
                401 => FormateadorErrores::respuestaDesdeCodigo(CodigoErrorNucleo::SesionInvalida),
                403 => FormateadorErrores::respuestaDesdeCodigo(CodigoErrorNucleo::RolSinPermiso),
                404 => FormateadorErrores::respuestaDesdeCodigo(CodigoErrorNucleo::RecursoNoEncontrado),
                422 => FormateadorErrores::respuestaDesdeCodigo(CodigoErrorNucleo::ValidacionFallida),
                503 => FormateadorErrores::respuestaDesdeCodigo(CodigoErrorNucleo::ProveedorNoResponde),
                default => FormateadorErrores::respuesta(
                    'ERR-'.$statusCode,
                    $statusCode >= 500 ? CodigoErrorNucleo::ProveedorNoResponde->mensaje() : $e->getMessage(),
                    $statusCode,
                    null
                ),
            };
        }

        // 8. Error 500 interno del sistema (no exponer detalles al usuario final por seguridad)
        return FormateadorErrores::respuesta(
            CodigoErrorNucleo::ProveedorNoResponde->value,
            CodigoErrorNucleo::ProveedorNoResponde->mensaje(),
            500,
            null
        );
    }

    /**
     * Determina si la excepción debe registrarse en el log diario de sistema.
     * Según la Sección 5.5: solo errores 5xx o fallas de proveedor (ERR-006).
     * Los 4xx esperados (401, 403, 404, 422) NO deben registrarse como error de sistema.
     */
    public static function debeRegistrarseEnLog(Throwable $e): bool
    {
        if ($e instanceof ValidationException || $e instanceof AuthenticationException || $e instanceof AuthorizationException) {
            return false;
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException || $e instanceof AccessDeniedHttpException) {
            return false;
        }

        if ($e instanceof ErrorEstandarInterface && in_array($e->getCodigoHttp(), self::CODIGOS_HTTP_NO_LOG, true)) {
            return false;
        }

        if ($e instanceof HttpExceptionInterface && in_array($e->getStatusCode(), self::CODIGOS_HTTP_NO_LOG, true)) {
            return false;
        }

        return true;
    }

    /**
     * Registra un error de sistema (5xx o no esperado) en el archivo de log diario de Laravel
     * (storage/logs/laravel-YYYY-MM-DD.log) con código, módulo de origen, traza y petición.
     */
    public static function registrarEnLogDiario(Throwable $e, ?Request $request = null): void
    {
        if (! self::debeRegistrarseEnLog($e)) {
            return;
        }

        $codigo = self::resolverCodigoError($e);
        $modulo = self::detectarModulo($e, $request);

        $contexto = [
            'codigo' => $codigo,
            'modulo_origen' => $modulo,
            'peticion' => $request ? [
                'metodo' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'parametros' => $request->except([
                    'password',
                    'contrasena',
                    'password_confirmation',
                    'token',
                    'secret',
                ]),
            ] : null,
            'traza' => $e->getTraceAsString(),
        ];

        try {
            Log::channel('daily')->error(
                sprintf('[%s][%s] %s', $modulo, $codigo, $e->getMessage()),
                $contexto
            );
        } catch (Throwable) {
            // Si el canal daily falla, recurrir al logger por defecto
            Log::error(
                sprintf('[%s][%s] %s', $modulo, $codigo, $e->getMessage()),
                $contexto
            );
        }
    }

    /**
     * Detecta el módulo de origen de la excepción mediante la interfaz del error,
     * el namespace de la clase o la ruta de la petición.
     */
    public static function detectarModulo(Throwable $e, ?Request $request = null): string
    {
        if ($e instanceof ErrorEstandarInterface && $e->getModulo() !== null && $e->getModulo() !== 'Nucleo') {
            return $e->getModulo();
        }

        $className = get_class($e);

        if (str_contains($className, 'M1_Portal')) {
            return 'M1_Portal';
        }
        if (str_contains($className, 'M2_Comercios')) {
            return 'M2_Comercios';
        }
        if (str_contains($className, 'M3_Catalogo')) {
            return 'M3_Catalogo';
        }
        if (str_contains($className, 'M4_Inventario')) {
            return 'M4_Inventario';
        }
        if (str_contains($className, 'M5_Pedidos')) {
            return 'M5_Pedidos';
        }
        if (str_contains($className, 'M6_Pagos')) {
            return 'M6_Pagos';
        }
        if (str_contains($className, 'M7_Contabilidad')) {
            return 'M7_Contabilidad';
        }

        // Inspeccionar el stack trace por si la excepción se originó en un módulo
        foreach ($e->getTrace() as $traceItem) {
            $file = $traceItem['file'] ?? '';
            foreach (['M1_Portal', 'M2_Comercios', 'M3_Catalogo', 'M4_Inventario', 'M5_Pedidos', 'M6_Pagos', 'M7_Contabilidad'] as $mod) {
                if (str_contains($file, $mod)) {
                    return $mod;
                }
            }
        }

        // Inspeccionar la URL solicitada
        if ($request) {
            $path = strtolower($request->path());
            if (str_contains($path, 'm1')) {
                return 'M1_Portal';
            }
            if (str_contains($path, 'm2')) {
                return 'M2_Comercios';
            }
            if (str_contains($path, 'm3')) {
                return 'M3_Catalogo';
            }
            if (str_contains($path, 'm4')) {
                return 'M4_Inventario';
            }
            if (str_contains($path, 'm5')) {
                return 'M5_Pedidos';
            }
            if (str_contains($path, 'm6')) {
                return 'M6_Pagos';
            }
            if (str_contains($path, 'm7')) {
                return 'M7_Contabilidad';
            }
        }

        return 'Nucleo';
    }

    /**
     * Resuelve el código de error textual de la excepción.
     */
    private static function resolverCodigoError(Throwable $e): string
    {
        if ($e instanceof ErrorEstandarInterface) {
            return $e->getCodigo();
        }

        if (property_exists($e, 'codigo') && is_string($e->codigo)) {
            return $e->codigo;
        }

        if ($e instanceof HttpExceptionInterface) {
            return 'ERR-'.$e->getStatusCode();
        }

        return CodigoErrorNucleo::ProveedorNoResponde->value;
    }
}
