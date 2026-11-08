<?php

namespace Tests\Doubles\M5;

use App\Services\M5_Pedidos\Dobles\ComerciosStub;

/**
 * Doble de prueba de M2 que devuelve un costo de envío distinto por comercio.
 *
 * ComerciosStub usa un único costo de envío para todos los comercios, lo que no permite
 * comprobar que el envío se suma comercio por comercio (CP-003).
 *
 * Solo los comercios incluidos en conCostosDeEnvio() tienen un costo propio; para los demás
 * se aplica el valor por defecto de ComerciosStub.
 */
class ComerciosEnvioPorComercio extends ComerciosStub
{
    /** @var array<int, float> */
    private array $costosPorComercio = [];

    /**
     * @param  array<int, float>  $costos  Costo de envío indexado por id_comercio.
     */
    public function conCostosDeEnvio(array $costos): self
    {
        $this->costosPorComercio = $costos;

        return $this;
    }

    /**
     * @return array{id_comercio: int, politicas: list<array<string, mixed>>, tarifas: list<array<string, mixed>>}
     */
    public function obtenerPoliticasEnvio(int $idComercio): array
    {
        if (! array_key_exists($idComercio, $this->costosPorComercio)) {
            return parent::obtenerPoliticasEnvio($idComercio);
        }

        return [
            'id_comercio' => $idComercio,
            'politicas' => [],
            'tarifas' => [
                [
                    'zona' => 'GAM',
                    'costo' => $this->costosPorComercio[$idComercio],
                    'moneda' => 'CRC',
                ],
            ],
        ];
    }
}
