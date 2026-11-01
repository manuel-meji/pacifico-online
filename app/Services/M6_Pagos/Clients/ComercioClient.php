<?php

namespace App\Services\M6_Pagos\Clients;

/** Puerto de M6 hacia M2. */
interface ComercioClient
{
    /** @throws \App\Exceptions\M6_Pagos\ErrorContratoException COMERCIO_NO_ENCONTRADO | DATOS_FISCALES_INCOMPLETOS */
    public function obtenerDatosFiscales(int $idComercio): DatosFiscalesDTO;
}
