<?php

namespace App\Services\M6_Pagos\Clients;

use App\Exceptions\M6_Pagos\ErrorContratoException;

/** Puerto de M6 hacia M2. */
interface ComercioClient
{
    /** @throws ErrorContratoException COMERCIO_NO_ENCONTRADO | DATOS_FISCALES_INCOMPLETOS */
    public function obtenerDatosFiscales(int $idComercio): DatosFiscalesDTO;
}
