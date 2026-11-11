<?php

declare(strict_types=1);

namespace Tests\Unit\M6_Pagos;

use App\Services\M6_Pagos\Adaptadores\AdaptadorM5;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\M5\PedidosContratoFake;

final class AdaptadorM5CargaDiferidaTest extends TestCase
{
    public function test_no_resuelve_m5_hasta_que_se_usa_y_lo_hace_una_sola_vez(): void
    {
        $resoluciones = 0;
        $m5 = new PedidosContratoFake;
        $fabrica = function () use (&$resoluciones, $m5): PedidosContratoFake {
            $resoluciones++;

            return $m5;
        };

        $adaptador = new AdaptadorM5($fabrica, $fabrica);
        $this->assertSame(0, $resoluciones);

        $this->assertSame('confirmado', $adaptador->consultarEstadoPedido(8942));
        $adaptador->consultarEstadoPedido(8942);

        $this->assertSame(1, $resoluciones);
    }
}
