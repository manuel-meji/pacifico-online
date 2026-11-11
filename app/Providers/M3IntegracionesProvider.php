<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Services\M3_Catalogo\ProductoService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Proveedor de integraciones de M3 Catálogo.
 *
 * Elige, para los servicios de M3 que dependen de M2, entre el doble
 * (app/Services/M3_Catalogo/Dobles) y el servicio real, según
 * config/integraciones.php (clave m3). Cambiar M3_M2_MODO=real en el .env
 * conecta M2 real sin tocar código. Es un enlace contextual: solo afecta a
 * las clases de M3 listadas en CONSUMIDORES_M3.
 */
class M3IntegracionesServiceProvider extends ServiceProvider
{
    /** @var array<string, class-string> */
    public const INTERFACES = [
        'm2' => ComerciosServiceInterface::class,
    ];

    /** @var list<class-string> */
    public const CONSUMIDORES_M3 = [
        ProductoService::class,
    ];

    public function register(): void
    {
        foreach (self::INTERFACES as $proveedor => $interfaz) {
            $this->app->when(self::CONSUMIDORES_M3)
                ->needs($interfaz)
                ->give(fn (Application $app): object => $this->resolver($app, $proveedor, $interfaz));
        }
    }

    /**
     * @param  class-string  $interfaz
     */
    private function resolver(Application $app, string $proveedor, string $interfaz): object
    {
        $configuracion = $app['config']->get("integraciones.m3.{$proveedor}");

        return match ($configuracion['modo'] ?? null) {
            'real' => $app->make($interfaz),
            'doble' => $this->doble($app, $configuracion['doble']),
            default => throw new InvalidArgumentException(
                "integraciones.m3.{$proveedor}.modo debe ser 'doble' o 'real'."
            ),
        };
    }

    /**
     * El doble se registra con alcance de petición para reutilizar su estado.
     *
     * @param  class-string  $clase
     */
    private function doble(Application $app, string $clase): object
    {
        if (! $app->bound($clase)) {
            $app->scoped($clase);
        }

        return $app->make($clase);
    }
}