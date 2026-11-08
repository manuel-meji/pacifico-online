<?php

namespace App\Providers;

use App\Events\M5_Pedidos\PedidoCancelado;
use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M5_Pedidos\SubpedidoCancelado;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Events\M6_Pagos\PagoRechazado;
use App\Jobs\M4_Inventario\VencerReservasJob;
use App\Listeners\M4_Inventario\ProcesarEventosPedidoListener;
use App\Services\M4_Inventario\Dominio\Valoracion\MetodoValoracion;
use App\Services\M4_Inventario\Dominio\Valoracion\PromedioPonderado;
use App\Services\M4_Inventario\Repositorios\EloquentExistenciaRepository;
use App\Services\M4_Inventario\Repositorios\EloquentMovimientoRepository;
use App\Services\M4_Inventario\Repositorios\EloquentReservaRepository;
use App\Services\M4_Inventario\Repositorios\ExistenciaRepositoryInterface;
use App\Services\M4_Inventario\Repositorios\MovimientoRepositoryInterface;
use App\Services\M4_Inventario\Repositorios\ReservaRepositoryInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Enlaces internos de M4 (figuras 4.6b y 4.6c del PC3): repositorios Eloquent,
 * método de valoración del kardex (Strategy; SC-M4-07: promedio ponderado),
 * listeners de los eventos de otros módulos y la tarea de vencimiento cada 60 s.
 * Las dependencias hacia otros módulos las enlaza M4IntegracionesServiceProvider.
 */
class InventarioServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ExistenciaRepositoryInterface::class => EloquentExistenciaRepository::class,
        ReservaRepositoryInterface::class => EloquentReservaRepository::class,
        MovimientoRepositoryInterface::class => EloquentMovimientoRepository::class,
        MetodoValoracion::class => PromedioPonderado::class,
    ];

    public function boot(): void
    {
        $listener = ProcesarEventosPedidoListener::class;
        Event::listen(PedidoConfirmado::class, [$listener, 'alConfirmarPedido']);
        Event::listen(PagoRechazado::class, [$listener, 'alRechazarPago']);
        Event::listen(PedidoCancelado::class, [$listener, 'alCancelarPedido']);
        Event::listen(SubpedidoDespachado::class, [$listener, 'alDespacharSubpedido']);
        Event::listen(SubpedidoCancelado::class, [$listener, 'alCancelarSubpedido']);

        // RNF-M4-007: vencimiento de reservas cada 60 s (php artisan schedule:work).
        $this->callAfterResolving(Schedule::class, function (Schedule $programa): void {
            $programa->job(new VencerReservasJob)->everyMinute()->name('m4-vencer-reservas');
        });
    }
}
