<?php

declare(strict_types=1);

namespace App\Policies\M2_Comercios;

use App\Models\M2_Comercios\SolicitudComercio;
use App\Models\Nucleo\Usuario;
use App\Models\Nucleo\VerificaRol;

class SolicitudComercioPolicy
{
    use VerificaRol;

    public function revisar(Usuario $usuario, SolicitudComercio $solicitud): bool
    {
        return $this->esEncargadoPlataforma($usuario);
    }

    public function evaluarDocumento(Usuario $usuario, SolicitudComercio $solicitud): bool
    {
        return $this->esEncargadoPlataforma($usuario);
    }

    public function rechazar(Usuario $usuario, SolicitudComercio $solicitud): bool
    {
        return $this->esEncargadoPlataforma($usuario);
    }

    public function aprobar(Usuario $usuario, SolicitudComercio $solicitud): bool
    {
        return $this->esEncargadoPlataforma($usuario);
    }
}
