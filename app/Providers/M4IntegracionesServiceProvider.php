<?php

namespace App\Providers;

use App\Contracts\M4_Inventario\Dependencias\CatalogoDependencia;
use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Services\M4_Inventario\Dependencias\CatalogoReal;
use App\Services\M4_Inventario\Dependencias\ComerciosReal;
use App\Services\M4_Inventario\Dependencias\NucleoReal;
use App\Services\M4_Inventario\InventarioService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Despliegue mínimo de M4.
 *
 * 1. Elige, para cada dependencia de M4 (M2, M3 y el núcleo), entre el doble y
 *    el servicio real según config/integraciones.php, clave m4. El servicio
 *    real se llama dentro del mismo proceso por la interfaz PHP del proveedor
 *    (sección 5.1 del PC3), nunca leyendo sus tablas.
 * 2. Enlaza InventarioModuleInterface con el servicio real de M4.
 *
 * M4 no expone rutas HTTP propias: el frontend usa Inertia, así que los
 * controladores de cada pantalla llaman a InventarioModuleInterface en el
 * servidor y le pasan los datos a la página.
 */
class M4IntegracionesServiceProvider extends ServiceProvider
{
    /**
     * Interfaz de cada dependencia y adaptador real que la implementa.
     *
     * @var array<string, array{0: class-string, 1: class-string}>
     */
    public const DEPENDENCIAS = [
        'm2' => [ComerciosDependencia::class, ComerciosReal::class],
        'm3' => [CatalogoDependencia::class, CatalogoReal::class],
        'nucleo' => [NucleoDependencia::class, NucleoReal::class],
    ];

    public function register(): void
    {
        $this->app->bind(InventarioModuleInterface::class, InventarioService::class);

        foreach (self::DEPENDENCIAS as $proveedor => [$interfaz, $real]) {
            $this->app->bind($interfaz, fn (Application $app): object => $this->resolver($app, $proveedor, $real));
        }
    }

    /**
     * @param  class-string  $real
     */
    private function resolver(Application $app, string $proveedor, string $real): object
    {
        $configuracion = $app['config']->get("integraciones.m4.{$proveedor}");

        return match ($configuracion['modo'] ?? null) {
            'real' => $app->make($real),
            'doble' => $app->make($configuracion['doble']),
            default => throw new InvalidArgumentException(
                "integraciones.m4.{$proveedor}.modo debe ser 'doble' o 'real'."
            ),
        };
    }
}
