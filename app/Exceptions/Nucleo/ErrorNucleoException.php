<?php

declare(strict_types=1);

namespace App\Exceptions\Nucleo;

use App\Contracts\Nucleo\ErrorEstandarInterface;
use App\Enums\Nucleo\CodigoErrorNucleo;
use App\Services\Nucleo\FormateadorErrores;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Excepción base del Núcleo Compartido con soporte para el formato de error estándar
 * (Sección 5.5 del PC3).
 *
 * Puede ser utilizada directamente por el núcleo o por los módulos para lanzar
 * excepciones con códigos propios formateadas consistentemente.
 */
class ErrorNucleoException extends RuntimeException implements ErrorEstandarInterface, Responsable
{
    /**
     * @param  array<string, mixed>|null  $detalles
     */
    public function __construct(
        public readonly string $codigo,
        public readonly int $http,
        string $mensaje,
        public readonly ?array $detalles = null,
        public readonly string $modulo = 'Nucleo',
    ) {
        parent::__construct($mensaje);
    }

    /**
     * ERR-001: Sesión inválida o expirada (HTTP 401).
     *
     * @param  array<string, mixed>|null  $detalles
     */
    public static function sesionInvalida(?string $mensaje = null, ?array $detalles = null): self
    {
        return new self(
            CodigoErrorNucleo::SesionInvalida->value,
            CodigoErrorNucleo::SesionInvalida->codigoHttp(),
            $mensaje ?? CodigoErrorNucleo::SesionInvalida->mensaje(),
            $detalles,
            'Nucleo'
        );
    }

    /**
     * ERR-002: Rol sin permiso para la operación (HTTP 403).
     *
     * @param  array<string, mixed>|null  $detalles
     */
    public static function rolSinPermiso(?string $mensaje = null, ?array $detalles = null): self
    {
        return new self(
            CodigoErrorNucleo::RolSinPermiso->value,
            CodigoErrorNucleo::RolSinPermiso->codigoHttp(),
            $mensaje ?? CodigoErrorNucleo::RolSinPermiso->mensaje(),
            $detalles,
            'Nucleo'
        );
    }

    /**
     * ERR-003: Recurso de otro comercio o de otro cliente (HTTP 404).
     * Regla RN-12 (Aislamiento): se responde siempre 404 para no revelar la existencia del recurso.
     *
     * @param  array<string, mixed>|null  $detalles
     */
    public static function recursoNoAccesible(?string $mensaje = null, ?array $detalles = null): self
    {
        return new self(
            CodigoErrorNucleo::RecursoNoAccesible->value,
            CodigoErrorNucleo::RecursoNoAccesible->codigoHttp(),
            $mensaje ?? CodigoErrorNucleo::RecursoNoAccesible->mensaje(),
            $detalles,
            'Nucleo'
        );
    }

    /**
     * ERR-004: Recurso no encontrado (HTTP 404).
     *
     * @param  array<string, mixed>|null  $detalles
     */
    public static function recursoNoEncontrado(?string $mensaje = null, ?array $detalles = null): self
    {
        return new self(
            CodigoErrorNucleo::RecursoNoEncontrado->value,
            CodigoErrorNucleo::RecursoNoEncontrado->codigoHttp(),
            $mensaje ?? CodigoErrorNucleo::RecursoNoEncontrado->mensaje(),
            $detalles,
            'Nucleo'
        );
    }

    /**
     * ERR-005: Validación de datos fallida (HTTP 422).
     *
     * @param  array<string, mixed>  $detalles
     */
    public static function validacionFallida(array $detalles, ?string $mensaje = null): self
    {
        return new self(
            CodigoErrorNucleo::ValidacionFallida->value,
            CodigoErrorNucleo::ValidacionFallida->codigoHttp(),
            $mensaje ?? CodigoErrorNucleo::ValidacionFallida->mensaje(),
            $detalles,
            'Nucleo'
        );
    }

    /**
     * ERR-006: Módulo proveedor no responde (HTTP 503).
     */
    public static function proveedorNoResponde(
        string $moduloProveedor,
        ?string $motivo = null,
        ?string $mensaje = null
    ): self {
        $detalles = array_filter([
            'modulo' => $moduloProveedor,
            'motivo' => $motivo,
        ]);

        return new self(
            CodigoErrorNucleo::ProveedorNoResponde->value,
            CodigoErrorNucleo::ProveedorNoResponde->codigoHttp(),
            $mensaje ?? CodigoErrorNucleo::ProveedorNoResponde->mensaje(),
            empty($detalles) ? null : $detalles,
            'Nucleo'
        );
    }

    /**
     * Permite a cualquier módulo construir su propia excepción de negocio con formato estándar.
     * Ejemplo: ErrorNucleoException::paraModulo('M4_Inventario', 'ERR-M4-001', 409, 'Existencia insuficiente').
     *
     * @param  array<string, mixed>|null  $detalles
     */
    public static function paraModulo(
        string $modulo,
        string $codigo,
        int $http,
        string $mensaje,
        ?array $detalles = null
    ): self {
        return new self($codigo, $http, $mensaje, $detalles, $modulo);
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
        return $this->http;
    }

    public function getDetalles(): ?array
    {
        return $this->detalles;
    }

    public function getModulo(): ?string
    {
        return $this->modulo;
    }

    /**
     * @return array{error: array{codigo: string, mensaje: string, detalles: array<string, mixed>|null}}
     */
    public function toArray(): array
    {
        return FormateadorErrores::formatear(
            $this->codigo,
            $this->getMessage(),
            $this->detalles
        );
    }

    /**
     * Renderizado directo como JsonResponse.
     *
     * @param  Request  $request
     */
    public function render($request): JsonResponse
    {
        return FormateadorErrores::respuesta(
            $this->codigo,
            $this->getMessage(),
            $this->http,
            $this->detalles
        );
    }

    /**
     * Soporte para interfaz Responsable.
     *
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return $this->render($request);
    }
}
