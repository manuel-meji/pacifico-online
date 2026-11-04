<?php

use App\Providers\AppServiceProvider;
use App\Providers\M1IntegracionesServiceProvider;
use App\Providers\M2ComerciosServiceProvider;
use App\Providers\M4IntegracionesServiceProvider;
use App\Providers\M5IntegracionesServiceProvider;

return [
    AppServiceProvider::class,
    M1IntegracionesServiceProvider::class,
    M2ComerciosServiceProvider::class,
    M4IntegracionesServiceProvider::class,
    M5IntegracionesServiceProvider::class,
];
