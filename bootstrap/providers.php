<?php

use App\Providers\AppServiceProvider;
use App\Providers\M1IntegracionesServiceProvider;
use App\Providers\M4IntegracionesServiceProvider;
use App\Providers\M5IntegracionesServiceProvider;

return [
    AppServiceProvider::class,
    M1IntegracionesServiceProvider::class,
    M4IntegracionesServiceProvider::class,
    M5IntegracionesServiceProvider::class,
];
