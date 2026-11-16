<?php

namespace App\Providers;

use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Events\M5_Pedidos\DevolucionAprobada;
use App\Http\Controllers\M5_Pedidos\ControladorDevoluciones;
use App\Listeners\M2_Comercios\RevertirComisionPorDevolucion;
use App\Listeners\M4_Inventario\ProcesarDevolucionAprobadaListener;
use App\Services\M5_Pedidos\DevolucionesService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Cableado de las devoluciones de subpedidos (RF-019, RF-020, RF-021).
 *
 *  - Monta routes/modules/m5_devoluciones.php con los mismos prefijos que m5_pedidos.php.
 *  - Enlaza M6 para DevolucionesService respetando integraciones.m5.m6 (real o doble).
 *  - Registra los listeners de M4 (reingreso a kardex) y M2 (reversión de comisión) para
 *    DevolucionAprobada, igual que M2ComerciosServiceProvider e InventarioServiceProvider.
 *  - Opcional: bloquea la ruta anterior de devolución (config devoluciones.bloquear_ruta_legada).
 *
 * Se agrega a bootstrap/providers.php.
 */
class M5DevolucionesServiceProvider extends ServiceProvider
{
    /** Mismos montajes que routes/web.php para m5_pedidos.php: [prefijo, prefijo de nombre]. */
    private const MONTAJES = [
        ['api/m5/v1', 'api.m5.v1.'],
        ['api/v1', 'api.v1.'],
        ['m5', 'm5.'],
    ];

    public function register(): void
    {
        $this->app->when(DevolucionesService::class)
            ->needs(PagosServiceInterface::class)
            ->give(function (Application $app): object {
                $configuracion = $app['config']->get('integraciones.m5.m6');

                if (($configuracion['modo'] ?? 'real') === 'doble' && ! empty($configuracion['doble'])) {
                    if (! $app->bound($configuracion['doble'])) {
                        $app->scoped($configuracion['doble']);
                    }

                    return $app->make($configuracion['doble']);
                }

                return $app->make(PagosServiceInterface::class);
            });
    }

    public function boot(): void
    {
        foreach (self::MONTAJES as [$prefijo, $nombre]) {
            Route::middleware('web')
                ->prefix($prefijo)
                ->name($nombre)
                ->group(base_path('routes/modules/m5_devoluciones.php'));
        }

        Event::listen(DevolucionAprobada::class, [ProcesarDevolucionAprobadaListener::class, 'alAprobarDevolucion']);
        Event::listen(DevolucionAprobada::class, [RevertirComisionPorDevolucion::class, 'alAprobarDevolucion']);

        if (config('devoluciones.bloquear_ruta_legada')) {
            // Después de que se carguen las rutas de la aplicación: una ruta con el mismo método y URI
            // reemplaza a la anterior en la colección del router.
            $this->app->booted(function (): void {
                foreach (self::MONTAJES as [$prefijo, $nombre]) {
                    Route::middleware('web')
                        ->prefix($prefijo)
                        ->name($nombre)
                        ->post('/subpedidos/{id_subpedido}/devolucion', [ControladorDevoluciones::class, 'rutaLegada'])
                        ->name('subpedidos.devolucion');
                }
            });
        }
    }
}
