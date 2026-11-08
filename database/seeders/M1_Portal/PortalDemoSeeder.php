<?php

namespace Database\Seeders\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\PortalPublicoException;
use App\Models\M1_Portal\CategoriaPortal;
use App\Models\M1_Portal\ComercioDestacado;
use App\Models\M1_Portal\ProductoDestacado;
use App\Services\M1_Portal\DTO\CategoriaDTO;
use App\Services\M1_Portal\DTO\ComercioDTO;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\M1_Portal\DTO\EstadoComercio;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use App\Services\M1_Portal\Integraciones\ComercioClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datos de M1 para la demostración: menú de categorías (categorias_portal) y destacados de la
 * portada (productos_destacados y comercios_destacados). Solo escribe en tablas de M1.
 *
 * No usa identificadores fijos: los toma de M2 y M3 por sus contratos, con los adaptadores de M1
 * (ComercioClient y CatalogoClient), según config/integraciones.php. En modo 'doble' siembra los
 * datos de prueba; en modo 'real', los de la base compartida. Si un proveedor no responde, se
 * omite esa parte y se avisa, sin fallar.
 *
 * Es idempotente: se puede ejecutar varias veces sin duplicar filas (una por categoría, producto o
 * comercio); cada ejecución deja los destacados vigentes por DIAS_VIGENCIA días desde hoy.
 *     php artisan db:seed --class="Database\Seeders\M1_Portal\PortalDemoSeeder"
 */
class PortalDemoSeeder extends Seeder
{
    public const MAXIMO_PRODUCTOS = 8;

    public const MAXIMO_COMERCIOS = 8;

    public const DIAS_VIGENCIA = 30;

    /** Productos que se piden a M3 por cada categoría principal. */
    private const PRODUCTOS_POR_CATEGORIA = 4;

    public function run(): void
    {
        $catalogo = app(CatalogoClient::class);
        $comercios = app(ComercioClient::class);
        $desde = Carbon::now()->startOfDay();
        $hasta = $desde->copy()->addDays(self::DIAS_VIGENCIA)->endOfDay();

        $principales = $this->intentar('categorías (M3)', fn (): array => array_values(array_filter(
            $catalogo->listarCategorias(),
            fn (CategoriaDTO $categoria): bool => $categoria->idCategoriaPadre === null,
        ))) ?? [];

        DB::transaction(function () use ($principales, $catalogo, $comercios, $desde, $hasta): void {
            $this->sembrarCategorias($principales);
            $this->sembrarProductos($this->productos($catalogo, $principales), $desde, $hasta);
            $this->sembrarComercios($this->comercios($comercios), $desde, $hasta);
        });
    }

    /**
     * Una fila visible por categoría principal, en el orden en que la entrega M3.
     * Las subcategorías no necesitan fila: se muestran salvo que se oculten (RN-M1-09).
     *
     * @param  list<CategoriaDTO>  $principales
     */
    private function sembrarCategorias(array $principales): void
    {
        foreach ($principales as $posicion => $categoria) {
            CategoriaPortal::query()->updateOrCreate(
                ['id_categoria' => $categoria->idCategoria],
                ['orden_visualizacion' => $posicion + 1, 'visible_en_menu' => true],
            );
        }

        $this->informar('Categorías del menú: '.count($principales));
    }

    /**
     * Productos que M3 publica, repartidos entre las categorías principales.
     *
     * @param  list<CategoriaDTO>  $principales
     * @return list<int>
     */
    private function productos(CatalogoClient $catalogo, array $principales): array
    {
        $ids = [];

        foreach ($principales as $categoria) {
            $resultado = $this->intentar("productos de la categoría {$categoria->idCategoria} (M3)", fn () => $catalogo->buscarProductos(
                new CriterioBusquedaDTO(idCategoria: $categoria->idCategoria, porPagina: self::PRODUCTOS_POR_CATEGORIA),
            ));

            foreach ($resultado?->productos ?? [] as $producto) {
                $ids[$producto->idProducto] = $producto->idProducto;
            }
        }

        return array_slice(array_values($ids), 0, self::MAXIMO_PRODUCTOS);
    }

    /**
     * Comercios que M2 devuelve como habilitados (Activo).
     *
     * @return list<int>
     */
    private function comercios(ComercioClient $comercios): array
    {
        $listado = $this->intentar('comercios (M2)', fn () => $comercios->listarComerciosHabilitados(1, self::MAXIMO_COMERCIOS));

        return array_values(array_map(
            fn (ComercioDTO $comercio): int => $comercio->idComercio,
            array_filter($listado?->comercios ?? [], fn (ComercioDTO $comercio): bool => $comercio->estado === EstadoComercio::Activo),
        ));
    }

    /**
     * @param  list<int>  $ids
     */
    private function sembrarProductos(array $ids, Carbon $desde, Carbon $hasta): void
    {
        foreach ($ids as $posicion => $id) {
            ProductoDestacado::query()->updateOrCreate(
                ['id_producto' => $id],
                ['posicion' => $posicion + 1, 'fecha_inicio' => $desde, 'fecha_fin' => $hasta, 'activo' => true],
            );
        }

        $this->informar('Productos destacados: '.count($ids));
    }

    /**
     * @param  list<int>  $ids
     */
    private function sembrarComercios(array $ids, Carbon $desde, Carbon $hasta): void
    {
        foreach ($ids as $posicion => $id) {
            ComercioDestacado::query()->updateOrCreate(
                ['id_comercio' => $id],
                ['posicion' => $posicion + 1, 'fecha_inicio' => $desde, 'fecha_fin' => $hasta, 'activo' => true],
            );
        }

        $this->informar('Comercios destacados: '.count($ids));
    }

    /**
     * Ejecuta una consulta a M2 o M3. Si el proveedor falla, el adaptador lanza una excepción de M1
     * (ERR-006 también cuando el proveedor todavía no implementa la operación): se avisa y se devuelve null.
     *
     * @template T
     *
     * @param  callable(): T  $consulta
     * @return T|null
     */
    private function intentar(string $que, callable $consulta): mixed
    {
        try {
            return $consulta();
        } catch (PortalPublicoException $error) {
            $this->command?->warn("No se pudieron obtener {$que}: {$error->getMessage()}");

            return null;
        }
    }

    private function informar(string $mensaje): void
    {
        $this->command?->info($mensaje);
    }
}
