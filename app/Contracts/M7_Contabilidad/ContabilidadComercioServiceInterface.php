<?php

declare(strict_types=1);

namespace App\Contracts\M7_Contabilidad;

interface ContabilidadComercioServiceInterface extends ContabilidadServiceInterface
{
    /**
     * Uso HTTP de M7: limita la búsqueda a los asientos del comercio indicado.
     *
     * La respuesta usa el asiento de distribución del comercio y no expone
     * los importes globales del pago ni el cuadre de otros comercios.
     *
     * @return array{id_pago: int, registrado: bool, asiento_id: int|null, fecha_registro: string|null, subpedidos_registrados: list<int>, libro?: string}
     */
    public function consultarRegistroContablePagoDeComercio(int $idPago, int $idComercio): array;
}
