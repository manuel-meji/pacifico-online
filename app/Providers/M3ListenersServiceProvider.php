<?php

namespace App\Providers;

use App\Events\M2_Comercios\ComercioAprobado;
use App\Events\M2_Comercios\ComercioReactivado;
use App\Events\M2_Comercios\ComercioSuspendido;
use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Events\M4_Inventario\ExistenciaRepuesta;
use App\Listeners\M3_Catalogo\MarcarVariacionAgotada;
use App\Listeners\M3_Catalogo\MarcarVariacionRepuesta;
use App\Listeners\M3_Catalogo\SincronizarEstadoComercio;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registra los listeners de los eventos que M3 consume (M2 y M4).
 * No hay descubrimiento automatico de listeners en este repositorio.
 *
 * Los listeners se ejecutan en el mismo proceso que el publicador y atrapan sus propios
 * errores (ver AtiendeEventosSinInterrumpir): un fallo de M3 no debe afectar a M1, M2 ni M4.
 */
class M3ListenersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(ExistenciaAgotada::class, MarcarVariacionAgotada::class);
        Event::listen(ExistenciaRepuesta::class, MarcarVariacionRepuesta::class);

        Event::listen(ComercioAprobado::class, [SincronizarEstadoComercio::class, 'alAprobar']);
        Event::listen(ComercioReactivado::class, [SincronizarEstadoComercio::class, 'alReactivar']);
        Event::listen(ComercioSuspendido::class, [SincronizarEstadoComercio::class, 'alSuspender']);
    }
}
