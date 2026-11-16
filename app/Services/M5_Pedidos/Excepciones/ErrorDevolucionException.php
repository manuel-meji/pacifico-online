<?php

namespace App\Services\M5_Pedidos\Excepciones;

use DomainException;

/**
 * Error de negocio de las devoluciones de M5. El controlador lo traduce al formato de error
 * del núcleo: { error: { codigo, mensaje, detalles } }.
 */
class ErrorDevolucionException extends DomainException
{
    /**
     * @param  array<string, mixed>|null  $detalles
     */
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
        public readonly int $status = 409,
        public readonly ?array $detalles = null,
    ) {
        parent::__construct($mensaje);
    }

    public static function noEncontrada(string $que = 'Devolución'): self
    {
        return new self('ERR-004', "{$que} no encontrada", 404);
    }

    public static function noAutorizado(string $mensaje = 'No tiene permiso para operar sobre esta devolución.'): self
    {
        return new self('ERR-M5-010', $mensaje, 403);
    }

    /** @param  array<string, mixed>  $detalles */
    public static function validacion(array $detalles, string $mensaje = 'Los datos de la devolución no son válidos.'): self
    {
        return new self('ERR-M5-011', $mensaje, 422, $detalles);
    }

    public static function estadoInvalido(string $mensaje): self
    {
        return new self('ERR-M5-002', $mensaje, 409);
    }

    public static function yaEnCurso(string $mensaje): self
    {
        return new self('ERR-M5-012', $mensaje, 409);
    }
}
