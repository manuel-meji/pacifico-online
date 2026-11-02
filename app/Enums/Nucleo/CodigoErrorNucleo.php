<?php

declare(strict_types=1);

namespace App\Enums\Nucleo;

/**
 * Códigos de error reservados del Núcleo Compartido según la Sección 5.5 del PC3.
 *
 * Los códigos ERR-001 a ERR-006 son exclusivos del núcleo y no deben ser
 * utilizados por ningún módulo para errores de negocio propios.
 */
enum CodigoErrorNucleo: string
{
    case SesionInvalida = 'ERR-001';
    case RolSinPermiso = 'ERR-002';
    case RecursoNoAccesible = 'ERR-003';
    case RecursoNoEncontrado = 'ERR-004';
    case ValidacionFallida = 'ERR-005';
    case ProveedorNoResponde = 'ERR-006';

    /**
     * Mensaje estándar oficial para el usuario final.
     */
    public function mensaje(): string
    {
        return match ($this) {
            self::SesionInvalida => 'Tu sesión expiró, inicia sesión de nuevo.',
            self::RolSinPermiso => 'No se encuentra autorizado para realizar esta acción.',
            self::RecursoNoAccesible => 'El recurso solicitado no existe o no es accesible.',
            self::RecursoNoEncontrado => 'No se encontró lo que buscas.',
            self::ValidacionFallida => 'Revisa los datos marcados (detalle por campo en detalles).',
            self::ProveedorNoResponde => 'Servicio temporalmente no disponible, intentar de nuevo.',
        };
    }

    /**
     * Código de estado HTTP correspondiente según la Sección 5.5 del PC3.
     */
    public function codigoHttp(): int
    {
        return match ($this) {
            self::SesionInvalida => 401,
            self::RolSinPermiso => 403,
            self::RecursoNoAccesible => 404,
            self::RecursoNoEncontrado => 404,
            self::ValidacionFallida => 422,
            self::ProveedorNoResponde => 503,
        };
    }
}
