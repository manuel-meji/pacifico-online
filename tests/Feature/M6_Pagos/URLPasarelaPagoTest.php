<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos;

use App\Contracts\M6_Pagos\PagosServiceInterface;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

class UrlPasarelaPagoTest extends TestCase
{
    use PreparaM6;

    /** La fachada que usa M5 devuelve la pantalla de M6 con el pedido y su monto. */
    public function test_la_fachada_devuelve_la_pantalla_de_m6_con_pedido_y_monto(): void
    {
        config(['m6.url_frontend' => 'http://localhost:5173/']);

        $respuesta = app(PagosServiceInterface::class)->procesarPago(
            self::PEDIDO_CONFIRMADO,
            self::MONTO_PEDIDO,
            'tarjeta',
            ['idempotency_key' => 'm5-url-pantalla'],
        );

        $this->assertSame(
            'http://localhost:5173/m6.html?pedido='.self::PEDIDO_CONFIRMADO.'&monto=59840.00',
            $respuesta['urlPasarelaPago'],
        );
    }
}
