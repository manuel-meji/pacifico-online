<?php

namespace Tests\Feature\M3_Catalogo\Aceptacion;

use Tests\TestCase;

class M3_ConsultaEscenariosAceptacionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('m', 32)));
    }

    public function test_escenario_uno_queda_pendiente_de_datos_oficiales_y_servicios_m5(): void
    {
        $this->markTestSkipped(
            'Pendiente externo: seeder oficial de Jeaustin, buscarProductos, M5 y datos oficiales para verificar los seis productos, ₡10.000, ₡9.500, IVA 13% y total ₡110.960.'
        );
    }

    public function test_escenario_dos_queda_pendiente_del_listener_m3_de_existencia_agotada(): void
    {
        $this->markTestSkipped(
            'Pendiente externo: listener M3 para ExistenciaAgotada, evento confirmado y datos oficiales para verificar disponible=false en la variación 9 de la botella.'
        );
    }
}
