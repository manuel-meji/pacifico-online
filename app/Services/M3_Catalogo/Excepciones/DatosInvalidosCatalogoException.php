<?php

namespace App\Services\M3_Catalogo\Excepciones;

use RuntimeException;

/** ERR-005: algun filtro de buscarProductos no cumple el contrato (422). */
class DatosInvalidosCatalogoException extends RuntimeException
{
    public const CODIGO = 'ERR-005';

    /** @param array<string, string> $detalles campo => motivo */
    public function __construct(public readonly array $detalles)
    {
        parent::__construct('Los datos enviados no son validos.');
    }

    public function codigoError(): string
    {
        return self::CODIGO;
    }
}
