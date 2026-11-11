<?php

namespace App\Services\M4_Inventario\Excepciones;

use RuntimeException;

/**
 * Error de M4 con el formato estándar del núcleo (sección 5.5 del PC3):
 * {"error": {"codigo", "mensaje", "detalles"}}. Los códigos propios son los
 * ERR-M4-nnn de x-codigos-error del contrato v1.3.0.
 *
 * Quien llama a M4 por la interfaz PHP la captura y decide cómo mostrarla;
 * toArray() da el cuerpo en el formato del núcleo. En la API /api/m4/v1 la
 * convierte en respuesta el manejador de errores del núcleo (ManejadorErrores),
 * con el estado HTTP de $http.
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

    public static function reservaVencida(): self
    {
        return new self('ERR-M4-005', 409, 'La reserva venció y ya no puede confirmarse.');
    }

    public static function vinculoNoCorresponde(): self
    {
        return new self('ERR-M4-007', 409, 'El pedido o subpedido no corresponde a la reserva.');
    }

    public static function pedidoNoPagado(): self
    {
        return new self('ERR-M4-009', 409, 'El pedido aún no está pagado.');
    }

    public static function reingresoYaRegistrado(): self
    {
        return new self('ERR-M4-010', 409, 'El reingreso de esta reserva ya fue registrado.');
    }

    public static function recepcionNoVerificada(): self
    {
        return new self('ERR-M4-011', 409, 'La recepción física de la mercadería no está verificada.');
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
     * ERR-002: el rol o la autorización no permiten la operación.
     *
     * @param  array<string, mixed>|null  $detalles
     */
    public static function accesoDenegado(?string $mensaje = null, ?array $detalles = null): self
    {
        return new self('ERR-002', 403, $mensaje ?? 'No se encuentra autorizado para realizar esta acción.', $detalles);
    }

    public static function rangoFechasInvalido(): self
    {
        return new self('ERR-M4-012', 422, 'El rango de fechas no es válido.', [
            'fecha_inicio' => 'Use el formato AAAA-MM-DD; fecha_fin no puede ser anterior a fecha_inicio.',
        ]);
    }

    public static function conteoDesactualizado(): self
    {
        return new self('ERR-M4-016', 409, 'El conteo físico está desactualizado; vuelve a contar.');
    }

    public static function ajusteBajoComprometido(): self
    {
        return new self('ERR-M4-017', 409, 'El ajuste dejaría el stock por debajo de lo comprometido.');
    }

    public static function subpedidoSinRegistros(): self
    {
        return new self('ERR-M4-018', 404, 'No hay registros del subpedido en inventario.');
    }

    public static function salidasPendientes(): self
    {
        return new self('ERR-M4-019', 409, 'Las salidas del subpedido aún no están confirmadas; reintenta más tarde.');
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
