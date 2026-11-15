<?php

declare(strict_types=1);

namespace App\Contracts\M7_Contabilidad;

interface DirectorioComerciosM7GatewayInterface
{
    /**
     * Nombres comerciales de los comercios indicados. Omite los que el proveedor no reconoce.
     *
     * @param  list<int>  $idsComercio
     * @return array<int, string> id_comercio => nombre comercial
     */
    public function obtenerNombres(array $idsComercio): array;
}
