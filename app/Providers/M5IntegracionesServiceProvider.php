<?php

namespace App\Providers;

use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M5_Pedidos\CarritoServiceInterface;
use App\Contracts\M5_Pedidos\GestionPedidosServiceInterface;
use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Http\Controllers\M5_Pedidos\ControladorCarrito;
use App\Http\Controllers\M5_Pedidos\ControladorPedido;
use App\Services\M5_Pedidos\PedidosService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Proveedor de integraciones para el Módulo 5 (M5_Pedidos).
 *
 * Elige para los consumidores de M5 (PedidosService, ControladorCarrito, ControladorPedido)
 * entre el doble de prueba formal (app/Services/M5_Pedidos/Dobles) y el servicio real
 * según config/integraciones.php (clave m5).
 *
 * Aplica enlace contextual (contextual binding) para no interferir con las dependencias
 * de otros módulos.
 */
class M5IntegracionesServiceProvider extends ServiceProvider
{
    /**
     * Mapa de claves a interfaces de cada módulo proveedor externo.
     *
     * @var array<string, class-string>
     */
    public const INTERFACES = [
        'm1' => PortalServiceInterface::class,
        'm2' => ComerciosServiceInterface::class,
        'm3' => CatalogoServiceInterface::class,
        'm4' => InventarioServiceInterface::class,
        'm4_module' => InventarioModuleInterface::class,
        'm6' => PagosServiceInterface::class,
    ];

    /**
     * Clases consumidoras dentro del módulo M5 que reciben las dependencias.
     *
     * @var list<class-string>
     */
    public const CONSUMIDORES_M5 = [
        PedidosService::class,
        ControladorCarrito::class,
        ControladorPedido::class,
    ];

    public function register(): void
    {
        // Enlace de las interfaces internas de M5 a su servicio canónico
        $this->app->bind(CarritoServiceInterface::class, PedidosService::class);
        $this->app->bind(GestionPedidosServiceInterface::class, PedidosService::class);
        $this->app->bind(SubpedidosServiceInterface::class, PedidosService::class);

        // Enlace contextual de proveedores externos para M5
        foreach (self::INTERFACES as $clave => $interfaz) {
            $proveedor = ($clave === 'm4_module') ? 'm4' : $clave;

            $this->app->when(self::CONSUMIDORES_M5)
                ->needs($interfaz)
                ->give(fn (Application $app): object => $this->resolver($app, $proveedor, $interfaz));
        }
    }

    /**
     * Resuelve el servicio real o el doble formal según la configuración.
     *
     * @param  class-string  $interfaz
     */
    private function resolver(Application $app, string $proveedor, string $interfaz): object
    {
        if ($app->resolved($interfaz)) {
            return $app->make($interfaz);
        }

        $configuracion = $app['config']->get("integraciones.m5.{$proveedor}");

        return match ($configuracion['modo'] ?? null) {
            'real' => $app->make($interfaz),
            'doble' => $this->doble($app, $configuracion['doble']),
            default => throw new InvalidArgumentException(
                "integraciones.m5.{$proveedor}.modo debe ser 'doble' o 'real'."
            ),
        };
    }

    /**
     * Instancia o reutiliza el doble de prueba en el alcance de la petición.
     *
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
