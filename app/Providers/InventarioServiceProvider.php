<?php

namespace App\Providers;

use App\Services\M4_Inventario\Repositorios\EloquentExistenciaRepository;
use App\Services\M4_Inventario\Repositorios\EloquentMovimientoRepository;
use App\Services\M4_Inventario\Repositorios\EloquentReservaRepository;
use App\Services\M4_Inventario\Repositorios\ExistenciaRepositoryInterface;
use App\Services\M4_Inventario\Repositorios\MovimientoRepositoryInterface;
use App\Services\M4_Inventario\Repositorios\ReservaRepositoryInterface;
use Illuminate\Support\ServiceProvider;

/**
 * Enlaces internos de M4 (figura 4.6b del PC3): repositorios Eloquent. Las
 * dependencias hacia otros módulos las enlaza M4IntegracionesServiceProvider.
 */
class InventarioServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ExistenciaRepositoryInterface::class => EloquentExistenciaRepository::class,
        ReservaRepositoryInterface::class => EloquentReservaRepository::class,
        MovimientoRepositoryInterface::class => EloquentMovimientoRepository::class,
    ];
}
