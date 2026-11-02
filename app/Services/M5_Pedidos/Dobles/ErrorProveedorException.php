<?php

namespace App\Services\M5_Pedidos\Dobles;

use Exception;

/**
 * Excepción estándar para simular errores de proveedores externos en M5
 * siguiendo el estándar del núcleo (códigos de error y códigos HTTP 409, 422, 402, 404).
 */
class ErrorProveedorException extends Exception
{
    /**
     * @param  array<string, mixed>|null  $detalles
     */
    public function __construct(
        public readonly string $codigoError,
        string $mensaje,
        int $codigoHttp = 400,
        public readonly ?array $detalles = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($mensaje, $codigoHttp, $previous);
    }

    public static function comercioSuspendido(string $mensaje = 'Comercio suspendido'): self
    {
        return new self('ERR-M2-002', $mensaje, 409, ['estado' => 'suspendido']);
    }

    public static function noEncontrado(string $mensaje = 'Variación inexistente o no disponible'): self
    {
        return new self('ERR-M3-004', $mensaje, 422, ['recurso' => 'variacion']);
    }

    public static function sinStock(string $mensaje = 'Existencia insuficiente para reserva'): self
    {
        return new self('ERR-M4-001', $mensaje, 409, ['stock' => 0]);
    }

    public static function pagoRechazado(string $mensaje = 'Pago rechazado por la pasarela'): self
    {
        return new self('ERR-M6-001', $mensaje, 402, ['estado_pago' => 'rechazado']);
    }

    /**
     * @param  array<string, mixed>  $detalles
     */
    public static function datosInvalidos(string $mensaje = 'Datos inválidos', array $detalles = []): self
    {
        return new self('ERR-005', $mensaje, 422, $detalles);
    }
}
