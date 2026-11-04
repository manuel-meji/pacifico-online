<?php

namespace App\Services\M1_Portal\Integraciones;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Services\M1_Portal\DTO\Disponibilidad;
use App\Services\M1_Portal\DTO\DisponibilidadDTO;

/**
 * Adaptador del contrato de M4 Inventario (consultarDisponibilidadProductoLote
 * y consultarDisponibilidadLote): traduce el estado de M4 ('disponible' o
 * 'agotado') a Disponibilidad, guarda cada resultado en la caché, aplica las
 * marcas de Agotado que llegaron por eventos y corta la espera a los 3 s.
 *
 * Son consultas públicas de M4: no aplican el filtro por comercio de RN-12.
 *
 * Diferencia con el diagrama del PC3: los lotes de M4 exigen id_comercio en
 * cada elemento, así que consultarDisponibilidadProductos recibe
 * id_producto → id_comercio, y consultarDisponibilidadVariaciones recibe el
 * producto y el comercio al que pertenecen las variaciones (las de una ficha).
 */
class InventarioClient
{
    use LlamaProveedor;

    private const MAXIMO_LOTE_M4 = 100;

    public function __construct(
        private InventarioModuleInterface $contrato,
        private CachePortal $cache,
    ) {
        $this->cargarTiempoMaximo();
    }

    /**
     * Disponibilidad por producto (resultados, destacados y micrositio): un
     * producto está disponible si alguna de sus variaciones tiene unidades.
     *
     * @param  array<int, int>  $idsProducto  id_producto → id_comercio.
     * @return list<DisponibilidadDTO> En el mismo orden de $idsProducto.
     */
    public function consultarDisponibilidadProductos(array $idsProducto): array
    {
        $estados = [];
        $faltantes = [];

        foreach ($idsProducto as $idProducto => $idComercio) {
            $guardado = $this->cache->obtener(sprintf(CachePortal::CLAVE_DISPONIBILIDAD_PRODUCTO, $idProducto));

            if ($guardado === null) {
                $faltantes[] = ['id_comercio' => $idComercio, 'id_producto' => $idProducto];
            } else {
                $estados[$idProducto] = $guardado;
            }
        }

        foreach (array_chunk($faltantes, self::MAXIMO_LOTE_M4) as $lote) {
            $respuesta = $this->llamar('M4', fn (): array => $this->contrato->consultarDisponibilidadProductoLote($lote));

            foreach ($respuesta as $item) {
                $estados[$item['id_producto']] = $item['estado_disponibilidad'];
                $this->cache->guardar(sprintf(CachePortal::CLAVE_DISPONIBILIDAD_PRODUCTO, $item['id_producto']), $item['estado_disponibilidad']);
            }
        }

        $resultado = [];

        foreach (array_keys($idsProducto) as $idProducto) {
            $agotadoPorEvento = $this->cache->obtener(sprintf(CachePortal::CLAVE_AGOTADO_PRODUCTO, $idProducto)) === true;

            $resultado[] = new DisponibilidadDTO(
                idProducto: $idProducto,
                idVariacion: null,
                estado: $agotadoPorEvento ? Disponibilidad::Agotado : $this->traducir($estados[$idProducto] ?? null),
            );
        }

        return $resultado;
    }

    /**
     * Disponibilidad de las variaciones de un producto (ficha de producto).
     *
     * @param  list<int>  $idsVariacion
     * @return list<DisponibilidadDTO> En el mismo orden de $idsVariacion.
     */
    public function consultarDisponibilidadVariaciones(int $idProducto, int $idComercio, array $idsVariacion): array
    {
        $estados = [];
        $faltantes = [];

        foreach ($idsVariacion as $idVariacion) {
            $guardado = $this->cache->obtener(sprintf(CachePortal::CLAVE_DISPONIBILIDAD_VARIACION, $idVariacion));

            if ($guardado === null) {
                $faltantes[] = ['id_comercio' => $idComercio, 'id_variacion' => $idVariacion];
            } else {
                $estados[$idVariacion] = $guardado;
            }
        }

        foreach (array_chunk($faltantes, self::MAXIMO_LOTE_M4) as $lote) {
            $respuesta = $this->llamar('M4', fn (): array => $this->contrato->consultarDisponibilidadLote($lote));

            foreach ($respuesta as $item) {
                $estados[$item['id_variacion']] = $item['estado_disponibilidad'];
                $this->cache->guardar(sprintf(CachePortal::CLAVE_DISPONIBILIDAD_VARIACION, $item['id_variacion']), $item['estado_disponibilidad']);
            }
        }

        $productoAgotado = $this->cache->obtener(sprintf(CachePortal::CLAVE_AGOTADO_PRODUCTO, $idProducto)) === true;

        return array_map(function (int $idVariacion) use ($idProducto, $estados, $productoAgotado): DisponibilidadDTO {
            $agotadoPorEvento = $productoAgotado
                || $this->cache->obtener(sprintf(CachePortal::CLAVE_AGOTADO_VARIACION, $idVariacion)) === true;

            return new DisponibilidadDTO(
                idProducto: $idProducto,
                idVariacion: $idVariacion,
                estado: $agotadoPorEvento ? Disponibilidad::Agotado : $this->traducir($estados[$idVariacion] ?? null),
            );
        }, array_values($idsVariacion));
    }

    /**
     * Lo que M4 no reconoce como disponible (incluido un producto o variación
     * sin registro de inventario) se muestra como Agotado.
     */
    private function traducir(?string $estado): Disponibilidad
    {
        return strtolower((string) $estado) === 'disponible' ? Disponibilidad::Disponible : Disponibilidad::Agotado;
    }
}
