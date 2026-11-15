<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad\Gateways;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M7_Contabilidad\DirectorioComerciosM7GatewayInterface;

final class M2DirectorioComerciosGateway implements DirectorioComerciosM7GatewayInterface
{
    private const MAXIMO_POR_LOTE = 50;

    public function __construct(
        private readonly ComerciosServiceInterface $comercios,
    ) {}

    public function obtenerNombres(array $idsComercio): array
    {
        $ids = array_values(array_unique(array_map('intval', $idsComercio)));
        $nombres = [];

        foreach (array_chunk($ids, self::MAXIMO_POR_LOTE) as $lote) {
            $resumen = $this->comercios->obtenerResumenComerciosLote($lote);

            foreach ($resumen['comercios'] ?? [] as $comercio) {
                $id = (int) ($comercio['id_comercio'] ?? 0);
                $nombre = trim((string) ($comercio['nombre_comercial'] ?? ''));

                if (in_array($id, $lote, true) && $nombre !== '') {
                    $nombres[$id] = $nombre;
                }
            }
        }

        return $nombres;
    }
}
