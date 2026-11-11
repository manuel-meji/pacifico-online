<?php

namespace Tests\Doubles\M4;

use InvalidArgumentException;
use RuntimeException;

/**
 * Error que lanzan los dobles que imitan los contratos de M2, M3 y M5, con el formato
 * estándar del núcleo {"error": {"codigo", "mensaje", "detalles"}} (sección 5.5 del PC3).
 *
 * Los códigos ERR-001 a ERR-006 son del núcleo y los usan todos los módulos; los mensajes son los
 * que publican M2 y M5 en sus contratos. Un módulo no puede usarlos como códigos propios: los suyos
 * siguen el formato ERR-M{n}-{nnn}.
 *
 * Los adaptadores reales de M4 (ComerciosReal, CatalogoReal) reciben este error como lo recibirían
 * del proveedor. Provisional: se reemplaza por la excepción base del núcleo cuando el Comité la publique.
 */
class ErrorProveedorException extends RuntimeException
{
    /** codigo => [HTTP, mensaje estándar]. */
    public const CATALOGO_NUCLEO = [
        'ERR-001' => [401, 'Tu sesión expiró, inicia sesión de nuevo.'],
        'ERR-002' => [403, 'No se encuentra autorizado para realizar esta acción.'],
        'ERR-003' => [404, 'El recurso solicitado no existe o no es accesible.'],
        'ERR-004' => [404, 'No se encontró lo que buscas.'],
        'ERR-005' => [422, 'Revisa los datos marcados (detalle por campo en detalles).'],
        'ERR-006' => [503, 'Servicio temporalmente no disponible, intentar de nuevo.'],
    ];

    /**
     * @param  array<string, mixed>|null  $detalles
     */
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
        public readonly int $estadoHttp,
        public readonly ?array $detalles = null,
    ) {
        parent::__construct($mensaje);
    }

    /**
     * Error del núcleo con su HTTP y su mensaje estándar.
     *
     * @param  array<string, mixed>|null  $detalles
     */
    public static function delNucleo(string $codigo, ?array $detalles = null, ?string $mensaje = null): self
    {
        if (! array_key_exists($codigo, self::CATALOGO_NUCLEO)) {
            throw new InvalidArgumentException("ERR-001 a ERR-006 son los únicos códigos del núcleo; recibido: {$codigo}.");
        }

        [$http, $mensajeEstandar] = self::CATALOGO_NUCLEO[$codigo];

        return new self($codigo, $mensaje ?? $mensajeEstandar, $http, $detalles);
    }

    /** ERR-001 (401): no hay sesión o expiró. */
    public static function sinSesion(): self
    {
        return self::delNucleo('ERR-001');
    }

    /** ERR-002 (403): el rol o la autorización no permiten la operación; el motivo va en el mensaje. */
    public static function accesoDenegado(string $mensaje, ?array $detalles = null): self
    {
        return self::delNucleo('ERR-002', $detalles, $mensaje);
    }

    /** ERR-003 (404): el recurso pertenece a otro comercio; se responde como si no existiera (RN-12). */
    public static function recursoNoAccesible(): self
    {
        return self::delNucleo('ERR-003');
    }

    /** ERR-004 (404): el recurso no existe. */
    public static function noEncontrado(?string $mensaje = null): self
    {
        return self::delNucleo('ERR-004', null, $mensaje);
    }

    /** ERR-005 (422): parámetros de entrada inválidos, con el mapa campo → mensaje. */
    public static function datosInvalidos(array $detalles): self
    {
        return self::delNucleo('ERR-005', $detalles);
    }

    /** ERR-006 (503): el módulo proveedor no respondió. */
    public static function noDisponible(string $modulo): self
    {
        return self::delNucleo('ERR-006', ['modulo' => $modulo]);
    }

    public static function esCodigoDeModulo(string $codigo): bool
    {
        return (bool) preg_match('/^ERR-M[1-7]-\d{3}$/', $codigo);
    }

    /**
     * @return array{error: array{codigo: string, mensaje: string, detalles: array<string, mixed>|null}}
     */
    public function toArray(): array
    {
        return ['error' => ['codigo' => $this->codigo, 'mensaje' => $this->getMessage(), 'detalles' => $this->detalles]];
    }
}
