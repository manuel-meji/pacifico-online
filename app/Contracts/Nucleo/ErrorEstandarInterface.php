<?php

declare(strict_types=1);

namespace App\Contracts\Nucleo;

/**
 * Contrato base para respuestas y excepciones que cumplen con el formato estándar
 * de error de Pacíficos Online (Sección 5.5 del PC3).
 */
interface ErrorEstandarInterface
{
    /**
     * Retorna el código de error (ej. ERR-001 o ERR-M{n}-{nnn}).
     */
    public function getCodigo(): string;

    /**
     * Retorna el mensaje de error amigable para el usuario.
     */
    public function getMensaje(): string;

    /**
     * Retorna el código de estado HTTP (ej. 401, 403, 404, 422, 503).
     */
    public function getCodigoHttp(): int;

    /**
     * Retorna detalles adicionales estructurados (ej. errores de validación por campo) o null.
     *
     * @return array<string, mixed>|null
     */
    public function getDetalles(): ?array;

    /**
     * Retorna el módulo de origen de la excepción (ej. 'Nucleo', 'M4_Inventario').
     */
    public function getModulo(): ?string;

    /**
     * Retorna el arreglo serializado con el formato estándar:
     * {"error": {"codigo": string, "mensaje": string, "detalles": ?array}}
     *
     * @return array{error: array{codigo: string, mensaje: string, detalles: array<string, mixed>|null}}
     */
    public function toArray(): array;
}
