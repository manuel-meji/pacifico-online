<?php

use App\Providers\AppServiceProvider;
use App\Providers\InventarioServiceProvider;
use App\Providers\M1IntegracionesServiceProvider;
use App\Providers\M2ComerciosServiceProvider;
use App\Providers\M4IntegracionesServiceProvider;
use App\Providers\M5IntegracionesServiceProvider;
use App\Providers\M6_PagosServiceProvider;
use App\Providers\M7ContabilidadServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;

return [
    AppServiceProvider::class,
    SanctumServiceProvider::class,
    M1IntegracionesServiceProvider::class,
    M2ComerciosServiceProvider::class,
    M4IntegracionesServiceProvider::class,
    InventarioServiceProvider::class,
    M5IntegracionesServiceProvider::class,
    M6_PagosServiceProvider::class,
    M7ContabilidadServiceProvider::class,
];
