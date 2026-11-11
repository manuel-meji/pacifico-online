<?php

declare(strict_types=1);

namespace App\Exceptions\M6_Pagos;

use App\Contracts\Nucleo\ErrorEstandarInterface;
use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Services\Nucleo\FormateadorErrores;
use RuntimeException;

/**
 * Error de negocio de M6 con el formato estándar del Núcleo.
 *
 * Uso: throw new ErrorContratoException('MONTO_INCONSISTENTE');
 *  - $e->codigo          → código del contrato (lo que leen los dobles y las pruebas).
 *  - $e->getCodigo()     → ERR-M6-nnn (lo que viaja en la respuesta JSON).
 *  - ManejadorErrores la convierte en {"error": {"codigo", "mensaje", "detalles"}}.
 */
class ErrorContratoException extends RuntimeException implements ErrorEstandarInterface
{
    public readonly string $codigo;

    /** @var array<string, mixed>|null */
    private readonly ?array $detalles;

    /**
     * @param  array<string, mixed>|null  $detalles
     */
    public function __construct(string $codigo, ?string $mensaje = null, ?array $detalles = null)
    {
        $this->codigo = $codigo;
        $this->detalles = ['motivo' => $codigo] + ($detalles ?? []);

        parent::__construct($mensaje ?? (CodigoErrorM6::tryFrom($codigo)?->mensaje() ?? 'Error del módulo de pagos.'));
    }

    public static function de(CodigoErrorM6 $codigo, ?string $mensaje = null, ?array $detalles = null): self
    {
        return new self($codigo->value, $mensaje, $detalles);
    }

    public function getCodigo(): string
    {
        return CodigoErrorM6::tryFrom($this->codigo)?->codigoEstandar() ?? 'ERR-M6-000';
    }

    public function getMensaje(): string
    {
        return $this->getMessage();
    }

    public function getCodigoHttp(): int
    {
        return CodigoErrorM6::tryFrom($this->codigo)?->codigoHttp() ?? 422;
    }

    public function getDetalles(): ?array
    {
        return $this->detalles;
    }

    public function getModulo(): ?string
    {
        return 'M6_Pagos';
    }

    public function toArray(): array
    {
        return FormateadorErrores::formatear($this->getCodigo(), $this->getMessage(), $this->detalles);
    }
}
