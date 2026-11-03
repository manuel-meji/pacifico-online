<?php

namespace App\Providers;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Contracts\M5_Pedidos\CarritoServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Events\M4_Inventario\ExistenciaRepuesta;
use App\Listeners\M1_Portal\EventosComercioListener;
use App\Listeners\M1_Portal\EventosExistenciaListener;
use App\Services\M1_Portal\Integraciones\CarritoClient;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use App\Services\M1_Portal\Integraciones\ComercioClient;
use App\Services\M1_Portal\Integraciones\InventarioClient;
use App\Services\M1_Portal\PortalService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Elige, para los servicios de M1, entre el doble de prueba y el servicio real
 * de cada proveedor según config/integraciones.php.
 *
 * Usa enlace contextual: los demás módulos siguen recibiendo los servicios
 * reales registrados en AppServiceProvider.
 *
 * Pasar un proveedor al servicio real (tarea 19 del plan del PC4) es solo
 * cambiar su 'modo' a 'real' en config/integraciones.php o con la variable de
 * entorno M1_M2_MODO, M1_M3_MODO, M1_M4_MODO o M1_M5_MODO. Ningún adaptador ni
 * servicio de M1 cambia.
 *
 * También registra los listeners de los eventos que M1 consume de M2 y M4.
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
        'm4' => InventarioModuleInterface::class,
        'm5' => CarritoServiceInterface::class,
    ];

    /**
     * Clases de M1 que reciben los proveedores: los adaptadores de
     * app/Services/M1_Portal/Integraciones, que son los únicos que conocen las
     * interfaces de M2, M3, M4 y M5 (Vista 2 y 3 del PC3). Agregar aquí cada
     * clase nueva de M1 que dependa directamente de un proveedor.
     *
     * @var list<class-string>
     */
    public const CONSUMIDORES_M1 = [
        PortalService::class,
        CatalogoClient::class,
        ComercioClient::class,
        InventarioClient::class,
        CarritoClient::class,
    ];

    /**
     * Interfaz anterior de M5. CarritoFake la sigue implementando para que el
     * código que la pedía no se rompa mientras se migra a CarritoServiceInterface.
     */
    private const INTERFAZ_ANTERIOR_M5 = PedidosServiceInterface::class;

    public function register(): void
    {
        foreach (self::INTERFACES as $proveedor => $interfaz) {
            $this->app->when(self::CONSUMIDORES_M1)
                ->needs($interfaz)
                ->give(fn (Application $app): object => $this->resolver($app, $proveedor, $interfaz));
        }

        $this->app->when(self::CONSUMIDORES_M1)
            ->needs(self::INTERFAZ_ANTERIOR_M5)
            ->give(fn (Application $app): object => $this->resolver($app, 'm5', self::INTERFAZ_ANTERIOR_M5));
    }

    /**
     * Listeners de los eventos que consume M1 (x-eventos-consumidos del
     * contrato, Vista 8 del PC3). Los de M2 se registran por el nombre de
     * clase de config/integraciones.php porque M2 todavía no publica sus
     * eventos; cuando los publique, basta con que el nombre coincida.
     */
    public function boot(): void
    {
        $eventosM2 = $this->app['config']->get('integraciones.m1.eventos_m2', []);

        Event::listen($eventosM2['comercio_aprobado'], [EventosComercioListener::class, 'alAprobarComercio']);
        Event::listen($eventosM2['comercio_suspendido'], [EventosComercioListener::class, 'alSuspenderComercio']);
        Event::listen($eventosM2['comercio_reactivado'], [EventosComercioListener::class, 'alReactivarComercio']);

        Event::listen(ExistenciaAgotada::class, [EventosExistenciaListener::class, 'alAgotarExistencia']);
        Event::listen(ExistenciaRepuesta::class, [EventosExistenciaListener::class, 'alReponerExistencia']);
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
