<?php

declare(strict_types=1);

namespace App\Services\Nucleo;

use App\Enums\Nucleo\CodigoErrorNucleo;
use Illuminate\Http\JsonResponse;

/**
 * Generador y formateador de la estructura de error JSON estándar de la plataforma
 * según la Sección 5.5 del PC3.
 *
 * Estructura estándar:
 * {
 *   "error": {
 *     "codigo": "ERR-...",
 *     "mensaje": "...",
 *     "detalles": null
 *   }
 * }
 */
final class FormateadorErrores
{
    /**
     * Construye el arreglo asociativo con la estructura exacta de error requerida.
     *
     * @param  array<string, mixed>|null  $detalles
     * @return array{error: array{codigo: string, mensaje: string, detalles: array<string, mixed>|null}}
     */
    public static function formatear(string $codigo, string $mensaje, ?array $detalles = null): array
    {
        return [
            'error' => [
                'codigo' => $codigo,
                'mensaje' => $mensaje,
                'detalles' => $detalles,
            ],
        ];
    }

    /**
     * Crea una respuesta HTTP JsonResponse con la estructura de error y el código HTTP indicado.
     *
     * @param  array<string, mixed>|null  $detalles
     */
    public static function respuesta(
        string $codigo,
        string $mensaje,
        int $estadoHttp = 400,
        ?array $detalles = null
    ): JsonResponse {
        return response()->json(
            self::formatear($codigo, $mensaje, $detalles),
            $estadoHttp
        );
    }

    /**
     * Crea una respuesta HTTP JsonResponse a partir de un código reservado del Núcleo.
     *
     * @param  array<string, mixed>|null  $detalles
     */
    public static function respuestaDesdeCodigo(
        CodigoErrorNucleo $codigo,
        ?string $mensajePersonalizado = null,
        ?array $detalles = null
    ): JsonResponse {
        return self::respuesta(
            $codigo->value,
            $mensajePersonalizado ?? $codigo->mensaje(),
            $codigo->codigoHttp(),
            $detalles
        );
    }
}
