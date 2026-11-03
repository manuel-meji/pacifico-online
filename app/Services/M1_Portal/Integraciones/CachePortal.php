<?php

namespace App\Services\M1_Portal\Integraciones;

use Illuminate\Contracts\Cache\Repository;

/**
 * Caché de las respuestas de M2, M3 y M4 con vida máxima de 5 s (RNF-M1-06),
 * sobre la caché de Laravel. También guarda las marcas de Agotado que llegan
 * por los eventos de M4, con la misma vida, para que el portal no muestre
 * disponible algo que M4 ya avisó que se agotó mientras la respuesta en caché
 * sigue vigente.
 *
 * Las claves que usan los adaptadores están en las constantes CLAVE_*.
 */
class CachePortal
{
    /** Datos públicos de un comercio (M2): sprintf con id_comercio. */
    public const CLAVE_COMERCIO_PUBLICO = 'comercio.publico.%d';

    /** Resumen de un comercio en lote (M2): sprintf con id_comercio. */
    public const CLAVE_COMERCIO_RESUMEN = 'comercio.resumen.%d';

    /** Lista de marcas (M3). */
    public const CLAVE_MARCAS = 'catalogo.marcas';

    /** Lista plana de categorías (M3). */
    public const CLAVE_CATEGORIAS = 'catalogo.categorias';

    /** Disponibilidad de un producto (M4): sprintf con id_producto. */
    public const CLAVE_DISPONIBILIDAD_PRODUCTO = 'disponibilidad.producto.%d';

    /** Disponibilidad de una variación (M4): sprintf con id_variacion. */
    public const CLAVE_DISPONIBILIDAD_VARIACION = 'disponibilidad.variacion.%d';

    /** Marca de Agotado de un producto (evento de M4): sprintf con id_producto. */
    public const CLAVE_AGOTADO_PRODUCTO = 'agotado.producto.%d';

    /** Marca de Agotado de una variación (evento de M4): sprintf con id_variacion. */
    public const CLAVE_AGOTADO_VARIACION = 'agotado.variacion.%d';

    private const PREFIJO = 'm1.portal.';

    /**
     * Segundos de vida de cada entrada (máximo 5 por RNF-M1-06).
     */
    private int $tiempoVida;

    public function __construct(private Repository $cache)
    {
        $this->tiempoVida = min(5, (int) config('integraciones.m1.cache_segundos', 5));
    }

    /**
     * Valor guardado, o null si no existe o ya venció.
     */
    public function obtener(string $clave): mixed
    {
        return $this->cache->get(self::PREFIJO.$clave);
    }

    public function guardar(string $clave, mixed $valor): void
    {
        $this->cache->put(self::PREFIJO.$clave, $valor, $this->tiempoVida);
    }

    /**
     * Olvida los datos del comercio para que la siguiente consulta los pida a
     * M2 (eventos ComercioAprobado, ComercioSuspendido y ComercioReactivado).
     */
    public function invalidarComercio(int $idComercio): void
    {
        $this->olvidar(sprintf(self::CLAVE_COMERCIO_PUBLICO, $idComercio));
        $this->olvidar(sprintf(self::CLAVE_COMERCIO_RESUMEN, $idComercio));
    }

    /**
     * Marca la variación como Agotado, o el producto completo si no se indica
     * variación, y olvida la disponibilidad guardada del producto (evento
     * ExistenciaAgotada).
     */
    public function marcarAgotado(int $idProducto, ?int $idVariacion): void
    {
        if ($idVariacion === null) {
            $this->guardar(sprintf(self::CLAVE_AGOTADO_PRODUCTO, $idProducto), true);
        } else {
            $this->guardar(sprintf(self::CLAVE_AGOTADO_VARIACION, $idVariacion), true);
            $this->olvidar(sprintf(self::CLAVE_DISPONIBILIDAD_VARIACION, $idVariacion));
        }

        $this->olvidar(sprintf(self::CLAVE_DISPONIBILIDAD_PRODUCTO, $idProducto));
    }

    /**
     * Quita la marca de Agotado de la variación y del producto, y olvida su
     * disponibilidad guardada para que se vuelva a consultar a M4 (evento
     * ExistenciaRepuesta).
     */
    public function quitarAgotado(int $idProducto, ?int $idVariacion): void
    {
        $this->olvidar(sprintf(self::CLAVE_AGOTADO_PRODUCTO, $idProducto));
        $this->olvidar(sprintf(self::CLAVE_DISPONIBILIDAD_PRODUCTO, $idProducto));

        if ($idVariacion !== null) {
            $this->olvidar(sprintf(self::CLAVE_AGOTADO_VARIACION, $idVariacion));
            $this->olvidar(sprintf(self::CLAVE_DISPONIBILIDAD_VARIACION, $idVariacion));
        }
    }

    private function olvidar(string $clave): void
    {
        $this->cache->forget(self::PREFIJO.$clave);
    }
}
