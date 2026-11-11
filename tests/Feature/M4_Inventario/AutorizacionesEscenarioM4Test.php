<?php

namespace Tests\Feature\M4_Inventario;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las autorizaciones registradas en config/integraciones.php sirven para los datos del escenario
 * (comercio 1, sedes 1 y 2 de M2, variaciones 501 y 502 del seeder), de modo que el inventario
 * físico con diferencia se puede demostrar en el ambiente (RN-M4-011). Cada una se usa una sola vez.
 */
class AutorizacionesEscenarioM4Test extends TestCase
{
    use EsquemaInventarioPrueba;
    use SesionesPruebaM4;

    protected function setUp(): void
    {
        parent::setUp();
        config(['integraciones.m4.nucleo.modo' => 'real']);
        $this->crearEsquemaNucleo();
        $this->crearEsquemaInventario();
        $this->existencia([
            'id_comercio' => 1, 'id_producto' => 301, 'id_variacion' => 501, 'id_ubicacion' => 1,
            'cantidad_fisica' => 40, 'costo_promedio' => 8500,
        ]);
    }

    public function test_el_conteo_del_escenario_se_ajusta_con_su_autorizacion_una_sola_vez(): void
    {
        $this->actingAs($this->encargado(1), 'sanctum');
        $conteo = [
            'id_comercio' => 1, 'id_variacion' => 501, 'id_ubicacion' => 1, 'cantidad_sistema_vista' => 40,
            'cantidad_contada' => 38, 'motivo_ajuste' => 'merma', 'justificacion' => 'Conteo físico del escenario',
            'id_autorizacion' => 'autorizacion-ajuste-c1-0002',
        ];

        $this->postJson('/api/m4/v1/ajustes', $conteo)->assertCreated();
        $this->assertSame(38, (int) DB::table('existencias')->where('id_variacion', 501)->value('cantidad_fisica'));

        $this->postJson('/api/m4/v1/ajustes', ['cantidad_sistema_vista' => 38, 'cantidad_contada' => 36] + $conteo)
            ->assertForbidden()->assertJsonPath('error.codigo', 'ERR-002');
        $this->assertSame(38, (int) DB::table('existencias')->where('id_variacion', 501)->value('cantidad_fisica'));
    }
}
