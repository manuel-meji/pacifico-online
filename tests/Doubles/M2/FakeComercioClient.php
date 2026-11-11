<?php

namespace Tests\Doubles\M2;

use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Services\M6_Pagos\Clients\ComercioClient;
use App\Services\M6_Pagos\Clients\DatosFiscalesDTO;

/** Fake de M2. 11 y 12 válidos; 13 = datos fiscales incompletos; otro = no existe. */
final class FakeComercioClient implements ComercioClient
{
    public function obtenerDatosFiscales(int $idComercio): DatosFiscalesDTO
    {
        return match ($idComercio) {
            11 => new DatosFiscalesDTO(11, 'Comercio Pacífico Sur S.A.', '3101123456', 'inscrito'),
            12 => new DatosFiscalesDTO(12, 'Ferretería El Pacífico S.A.', '3101654321', 'no_inscrito'),
            13 => throw new ErrorContratoException('DATOS_FISCALES_INCOMPLETOS'),
            default => throw new ErrorContratoException('COMERCIO_NO_ENCONTRADO'),
        };
    }
}
