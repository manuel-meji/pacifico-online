<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class M7ContabilidadServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(
            base_path('routes/modules/m7_contabilidad_api.php')
        );
    }
}
