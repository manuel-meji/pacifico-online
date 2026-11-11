<?php

declare(strict_types=1);

namespace App\Exceptions\M3_Catalogo;

use App\Enums\Nucleo\CodigoErrorNucleo;
use App\Exceptions\Nucleo\ErrorNucleoException;

/**
 * Errores propios de M3 Catálogo (contrato v1.3.0): ERR-M3-001 a ERR-M3-004.
 * Extiende ErrorNucleoException, así que el ManejadorErrores del núcleo la
 * convierte sola en el formato ErrorEstandar.
 */
class ErrorCatalogoException extends ErrorNucleoException
{
    private const MODULO = 'M3_Catalogo';

    /** ERR-M3-001 (409): el SKU ya existe en el comercio (RN-M3-01). */
    public static function skuDuplicado(string $sku): self
    {
        return new self(
            'ERR-M3-001',
            409,
            'Ya existe una variación con el mismo SKU en este comercio.',
            ['sku' => $sku],
            self::MODULO
        );
    }

    /** ERR-M3-002 (422): producto relacionado inexistente, de otro comercio o el mismo (RN-M3-12). */
    public static function relacionadoInvalido(): self
    {
        return new self(
            'ERR-M3-002',
            422,
            'Los productos relacionados deben ser del mismo comercio.',
            null,
            self::MODULO
        );
    }

    /** ERR-M3-003 (409): el producto ya tiene 8 imágenes (RN-M3-09). */
    public static function limiteImagenes(): self
    {
        return new self(
            'ERR-M3-003',
            409,
            'El producto ya alcanzó el máximo de 8 imágenes.',
            null,
            self::MODULO
        );
    }

    /** ERR-M3-004 (409): el comercio está suspendido (RN-M3-07). */
    public static function comercioSuspendido(): self
    {
        return new self(
            'ERR-M3-004',
            409,
            'El comercio está suspendido.',
            null,
            self::MODULO
        );
    }

    /** ERR-006 (503): el proveedor (M2, M4) no responde. Lo usan los dobles de M3. */
    public static function proveedorNoDisponible(string $proveedor): self
    {
        return new self(
            CodigoErrorNucleo::ProveedorNoResponde->value,
            CodigoErrorNucleo::ProveedorNoResponde->codigoHttp(),
            CodigoErrorNucleo::ProveedorNoResponde->mensaje(),
            ['modulo' => $proveedor],
            self::MODULO
        );
    }
}