<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Contratos (Interfaces)
use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use App\Contracts\Nucleo\NucleoServiceInterface;

// Implementaciones (Servicios)
use App\Services\M1_Portal\PortalService;
use App\Services\M2_Comercios\ComerciosService;
use App\Services\M3_Catalogo\CatalogoService;
use App\Services\M4_Inventario\InventarioService;
use App\Services\M5_Pedidos\PedidosService;
use App\Services\M6_Pagos\PagosService;
use App\Services\M7_Contabilidad\ContabilidadService;
use App\Services\Nucleo\NucleoService;

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
    }

    /**
     * Inicialización de servicios de la aplicación.
     */
    public function boot(): void
    {
        //
    }
}
