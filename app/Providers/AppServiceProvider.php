<?php

namespace App\Providers;

use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
// Contratos (Interfaces)
use App\Contracts\M6_Pagos\PagosServiceInterface;
// Contratos (Interfaces)
use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Services\M1_Portal\PortalService;
use App\Services\M2_Comercios\ComerciosService;
use App\Services\M3_Catalogo\CatalogoService;
use App\Services\M4_Inventario\InventarioService;
use App\Services\M5_Pedidos\PedidosService;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM1;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM2;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM5;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM7;
use App\Services\M6_Pagos\Clients\ClienteClient;
use App\Services\M6_Pagos\Clients\ComercioClient;
use App\Services\M6_Pagos\Clients\ContabilidadClient;
use App\Services\M6_Pagos\Clients\PedidoClient;
// Implementaciones (Servicios)
use App\Services\M6_Pagos\PagosService;
// Implementaciones (Servicios)
use App\Services\M7_Contabilidad\ContabilidadService;
use App\Services\Nucleo\ContextoCliente;
use App\Services\Nucleo\ContextoComercio;
use App\Services\Nucleo\NucleoService;
use App\Services\Nucleo\ServicioIdentidad;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Tests\Doubles\M1\FakeClienteClient;
use Tests\Doubles\M2\FakeComercioClient;
use Tests\Doubles\M5\FakePedidoClient;
use Tests\Doubles\M7\FakeContabilidadClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registro de enlaces en el contenedor de dependencias de Laravel.
     * Vincula cada interfaz PHP a su implementación de servicio correspondiente.
     */
    public function register(): void
    {
        $this->app->bind(PortalServiceInterface::class, PortalService::class);
        $this->app->bind(ComerciosServiceInterface::class, ComerciosService::class);
        $this->app->bind(CatalogoServiceInterface::class, CatalogoService::class);
        $this->app->bind(InventarioServiceInterface::class, InventarioService::class);
        $this->app->bind(PedidosServiceInterface::class, PedidosService::class);
        $this->app->bind(PagosServiceInterface::class, PagosService::class);
        $this->app->bind(ContabilidadServiceInterface::class, ContabilidadService::class);
        $this->app->bind(NucleoServiceInterface::class, NucleoService::class);
        $this->app->bind(ServicioIdentidadInterface::class, ServicioIdentidad::class);

        // Una instancia por petición (RN-12, ADR-006): si no son scoped,
        // cada app(ContextoComercio::class) crea un ámbito nuevo y vacío.
        $this->app->scoped(ContextoComercio::class);
        $this->app->scoped(ContextoCliente::class);
        $this->registrarPuertosM6();
    }

    /**
     * Puertos de salida de M6 hacia M1, M2, M5 y M7
     */
    private function registrarPuertosM6(): void
    {
        $puertos = [
            PedidoClient::class => [FakePedidoClient::class, AdaptadorM5::class],
            ClienteClient::class => [FakeClienteClient::class, AdaptadorM1::class],
            ComercioClient::class => [FakeComercioClient::class, AdaptadorM2::class],
            ContabilidadClient::class => [FakeContabilidadClient::class, AdaptadorM7::class],
        ];

        foreach ($puertos as $puerto => [$doble, $adaptador]) {
            $this->app->scoped($puerto, fn (Application $app): object => $app->make(
                config('m6.usar_dobles', true) ? $doble : $adaptador
            ));
        }
    }

    /**
     * Inicialización de servicios de la aplicación.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(hash('sha256',
                Str::lower($request->string('correo')->toString()).'|'.$request->ip()
            ));
        });

        $this->loadMigrationsFrom(glob(database_path('migrations/*'), GLOB_ONLYDIR));
    }
}
