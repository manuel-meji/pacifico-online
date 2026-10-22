<?php

namespace App\Services\M4_Inventario\Excepciones;

use RuntimeException;

/**
 * Error de M4 con el formato estándar del núcleo (sección 5.5 del PC3):
 * {"error": {"codigo", "mensaje", "detalles"}}. Los códigos propios son los
 * ERR-M4-nnn de x-codigos-error del contrato v1.2.0.
 *
 * Quien llama a M4 (otro módulo o un controlador de Inertia) la captura y
 * decide cómo mostrarla; toArray() da el cuerpo en el formato del núcleo.
 */
class ErrorInventarioException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $detalles
     */
    public function __construct(
        public readonly string $codigo,
        public readonly int $http,
        string $mensaje,
        public readonly ?array $detalles = null,
    ) {
        parent::__construct($mensaje);
    }

    public static function existenciaInsuficiente(): self
    {
        return new self('ERR-M4-001', 409, 'No hay existencias suficientes para la cantidad solicitada.');
    }

    public static function existenciaNoEncontrada(): self
    {
        return new self('ERR-M4-002', 404, 'No hay existencias registradas para la variación en esa ubicación.');
    }

    public static function claveIdempotenciaReutilizada(): self
    {
        return new self('ERR-M4-003', 409, 'La clave de idempotencia ya se usó con otros datos.');
    }

    public static function reservaNoEncontrada(): self
    {
        return new self('ERR-M4-004', 404, 'No se encontró la reserva.');
    }

    public static function reservaYaConsumida(): self
    {
        return new self('ERR-M4-008', 409, 'La reserva ya se convirtió en salida y no puede liberarse.');
    }

    /**
     * ERR-003: el recurso es de otro comercio (aislamiento, RN-12); se responde como si no existiera.
     */
    public static function recursoNoAccesible(): self
    {
        return new self('ERR-003', 404, 'El recurso solicitado no existe o no es accesible.');
    }

    public static function variacionNoEncontrada(): self
    {
        return new self('ERR-M4-013', 404, 'No se encontró la variación.');
    }

    public static function ubicacionNoEncontrada(): self
    {
        return new self('ERR-M4-014', 404, 'No se encontró la ubicación.');
    }

    public static function comercioNoHabilitado(): self
    {
        return new self('ERR-M4-015', 409, 'El comercio no está habilitado para registrar movimientos.');
    }

    /**
     * ERR-005: parámetros inválidos, con el mapa campo → mensaje.
     *
     * @param  array<string, string>  $detalles
     */
    public static function datosInvalidos(array $detalles): self
    {
        return new self('ERR-005', 422, 'Revisa los datos marcados (detalle por campo en detalles).', $detalles);
    }

    /**
     * ERR-006: el módulo proveedor no respondió.
     */
    public static function dependenciaNoDisponible(string $modulo, ?string $motivo = null): self
    {
        return new self(
            'ERR-006',
            503,
            'Servicio temporalmente no disponible, intentar de nuevo.',
            array_filter(['modulo' => $modulo, 'motivo' => $motivo]),
        );
    }

    /**
     * @return array{error: array{codigo: string, mensaje: string, detalles: array<string, mixed>|null}}
     */
    public function toArray(): array
    {
        return [
            'error' => [
                'codigo' => $this->codigo,
                'mensaje' => $this->getMessage(),
                'detalles' => $this->detalles,
            ],
        ];
    }
}
