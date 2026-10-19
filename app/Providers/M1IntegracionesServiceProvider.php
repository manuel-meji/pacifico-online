<?php

namespace App\Providers;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Services\M1_Portal\PortalService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Elige, para los servicios de M1, entre el doble de prueba y el servicio real
 * de cada proveedor según config/integraciones.php.
 *
 * Usa enlace contextual: los demás módulos siguen recibiendo los servicios
 * reales registrados en AppServiceProvider.
 */
class M1IntegracionesServiceProvider extends ServiceProvider
{
    /**
     * Interfaz de cada proveedor, por su clave en config/integraciones.php.
     *
     * @var array<string, class-string>
     */
    private const INTERFACES = [
        'm2' => ComerciosServiceInterface::class,
        'm3' => CatalogoServiceInterface::class,
        'm4' => InventarioServiceInterface::class,
        'm5' => PedidosServiceInterface::class,
    ];

    /**
     * Servicios de M1 que reciben los proveedores. Agregar aquí cada servicio
     * nuevo de M1 que dependa de M2, M3, M4 o M5.
     *
     * @var list<class-string>
     */
    public const CONSUMIDORES_M1 = [
        PortalService::class,
    ];

    public function register(): void
    {
        foreach (self::INTERFACES as $proveedor => $interfaz) {
            $this->app->when(self::CONSUMIDORES_M1)
                ->needs($interfaz)
                ->give(fn (Application $app): object => $this->resolver($app, $proveedor, $interfaz));
        }
    }

    /**
     * El doble se registra con alcance de petición, para que el estado del
     * fake de M5 se comparta entre los servicios de M1 durante la petición.
     */
    private function resolver(Application $app, string $proveedor, string $interfaz): object
    {
        $configuracion = $app['config']->get("integraciones.m1.{$proveedor}");

        return match ($configuracion['modo'] ?? null) {
            'real' => $app->make($interfaz),
            'doble' => $this->doble($app, $configuracion['doble']),
            default => throw new InvalidArgumentException(
                "integraciones.m1.{$proveedor}.modo debe ser 'doble' o 'real'."
            ),
        };
    }

    /**
     * @param  class-string  $clase
     */
    private function doble(Application $app, string $clase): object
    {
        if (! $app->bound($clase)) {
            $app->scoped($clase);
        }

        return $app->make($clase);
    }
}
