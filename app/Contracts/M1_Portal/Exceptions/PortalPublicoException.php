<?php

namespace App\Contracts\M1_Portal\Exceptions;

use App\Contracts\Nucleo\ErrorEstandarInterface;
use RuntimeException;
use Throwable;

/**
 * Base de las excepciones del contrato de M1 (v1.1.0). Expone el código de
 * error que el manejador del núcleo pone en la respuesta estándar
 * {"error": {"codigo", "mensaje", "detalles"}}.
 *
 * Implementa ErrorEstandarInterface del núcleo, así que el manejador de errores
 * del núcleo la responde con el código HTTP y el formato estándar sin que los
 * controladores de M1 tengan que atraparla.
 *
 * Extiende RuntimeException, así que el código que atrapaba RuntimeException
 * con la versión 1.0.0 del contrato sigue funcionando.
 */
abstract class PortalPublicoException extends RuntimeException implements ErrorEstandarInterface
{
    /**
     * Código HTTP de cada código de error, según las respuestas del contrato.
     *
     * @var array<string, int>
     */
    private const CODIGOS_HTTP = [
        'ERR-003' => 404,
        'ERR-004' => 404,
        'ERR-005' => 422,
        'ERR-006' => 503,
        'ERR-M1-001' => 409,
        'ERR-M1-002' => 409,
        'ERR-M1-003' => 409,
        'ERR-M1-004' => 409,
        'ERR-M1-005' => 409,
        'ERR-M1-006' => 409,
    ];

    /**
     * Código de error del contrato (ERR-004, ERR-005, ERR-006 o ERR-M1-001).
     */
    protected string $codigo;

    public function __construct(?string $mensaje = null, ?Throwable $anterior = null)
    {
        parent::__construct($mensaje ?? $this->mensajePorDefecto(), 0, $anterior);
    }

    /**
     * Código de error que se responde al cliente.
     */
    public function codigo(): string
    {
        return $this->codigo;
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function getMensaje(): string
    {
        return $this->getMessage();
    }

    public function getCodigoHttp(): int
    {
        return self::CODIGOS_HTTP[$this->codigo] ?? 500;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDetalles(): ?array
    {
        return null;
    }

    public function getModulo(): ?string
    {
        return 'M1_Portal';
    }

    /**
     * @return array{error: array{codigo: string, mensaje: string, detalles: array<string, mixed>|null}}
     */
    public function toArray(): array
    {
        return [
            'error' => [
                'codigo' => $this->getCodigo(),
                'mensaje' => $this->getMensaje(),
                'detalles' => $this->getDetalles(),
            ],
        ];
    }

    /**
     * Mensaje que se usa cuando quien lanza la excepción no indica uno.
     */
    abstract protected function mensajePorDefecto(): string;
}
