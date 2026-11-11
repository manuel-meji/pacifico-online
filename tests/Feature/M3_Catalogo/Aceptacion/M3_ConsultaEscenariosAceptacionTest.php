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
            'Pendiente real: el seeder M3 actual contiene el mouse a ₡10.000, pero no contiene por sí solo el precio final ₡9.500 ni el flujo M5 requerido para verificar seis productos, IVA y total ₡110.960.'
        );
    }

    public function test_escenario_dos_queda_pendiente_del_listener_m3_de_existencia_agotada(): void
    {
        $this->markTestSkipped(
            'Pendiente real: el seeder M3 actual identifica la variación 9 como café en grano, no como botella. El listener ExistenciaAgotada sí está cubierto por pruebas M3, pero este escenario oficial no se marca aprobado con datos que no coinciden.'
        );
    }
}
