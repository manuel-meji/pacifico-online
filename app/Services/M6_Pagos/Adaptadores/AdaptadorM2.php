<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos\Adaptadores;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\M6_Pagos\Clients\ComercioClient;
use App\Services\M6_Pagos\Clients\DatosFiscalesDTO;

final class AdaptadorM2 implements ComercioClient
{
    public function __construct(private readonly ComerciosServiceInterface $comercios) {}

    public function obtenerDatosFiscales(int $idComercio): DatosFiscalesDTO
    {
        try {
            $datos = $this->comercios->obtenerDatosFiscalesComercio($idComercio);
        } catch (ErrorNucleoException $e) {
            // ERR-004 = el comercio no existe; ERR-006 (M2 caído) se propaga tal cual.
            if ($e->codigo === 'ERR-004') {
                throw ErrorContratoException::de(CodigoErrorM6::ComercioNoEncontrado);
            }

            throw $e;
        }

        $razonSocial = trim((string) ($datos['razon_social'] ?? ''));
        $cedula = trim((string) ($datos['cedula_juridica'] ?? ''));

        if ($razonSocial === '' || $cedula === '') {
            throw ErrorContratoException::de(CodigoErrorM6::DatosFiscalesIncompletos);
        }

        return new DatosFiscalesDTO(
            idComercio: (int) ($datos['id_comercio'] ?? $idComercio),
            razonSocial: $razonSocial,
            cedulaJuridica: $cedula,
            condicionContribuyente: ($datos['inscrito_como_contribuyente'] ?? false) ? 'inscrito' : 'no_inscrito',
        );
    }
}
