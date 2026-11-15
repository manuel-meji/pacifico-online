<?php

namespace Tests\Feature\M1_Portal;

use App\Services\M1_Portal\Dobles\CatalogoFake;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use Tests\Doubles\M1\CatalogoSoloDetalle;
use TypeError;

/**
 * El contenedor resuelve CatalogoClient con el proveedor de M3 tipado como
 * intersección (CatalogoConsultasInterface&CatalogoServiceInterface), vinculado
 * por el nombre del parámetro ($contrato) en M1IntegracionesServiceProvider.
 * Un doble que cumple ambas interfaces funciona; uno que solo cumple una se
 * rechaza al construir el cliente (el caso que advirtió M3).
 */
class CatalogoClientBindingTest extends PruebaM1
{
    public function test_resuelve_el_cliente_con_un_doble_que_cumple_ambas_interfaces(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoFake::class]);

        $cliente = $this->app->make(CatalogoClient::class);

        $this->assertInstanceOf(CatalogoClient::class, $cliente);
        $this->assertIsArray($cliente->listarCategorias());
    }

    public function test_rechaza_un_doble_que_solo_cumple_una_interfaz(): void
    {
        config(['integraciones.m1.m3.doble' => CatalogoSoloDetalle::class]);

        $this->expectException(TypeError::class);

        $this->app->make(CatalogoClient::class);
    }
}
