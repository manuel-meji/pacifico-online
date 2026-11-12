<?php

namespace Tests\Feature\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Models\Nucleo\Usuario;
use App\Services\M4_Inventario\Dobles\CatalogoStub;
use Database\Seeders\M4_Inventario\InventarioDemoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verificación del docente «Aislamiento por comercio» (RN-12) por la API: con la sesión
 * de un comercio se consulta o modifica un dato de otro y el núcleo no lo deja ver
 * (404 ERR-003). El aislamiento sale del núcleo: middleware EstablecerComercio +
 * Global Scope PerteneceAComercio sobre los modelos de M4.
 */
class AislamientoComercioHttpM4Test extends TestCase
{
    use EsquemaInventarioPrueba;
    use SesionesPruebaM4;

    private int $existenciaAjena;

    private string $reservaAjena;

    protected function setUp(): void
    {
        parent::setUp();
        config(['integraciones.m4.nucleo.modo' => 'real']);
        $this->crearEsquemaNucleo();
        $this->crearEsquemaInventario();
        $this->existencia(['id_comercio' => 1, 'id_variacion' => 10, 'id_ubicacion' => 1, 'cantidad_fisica' => 5]);
        $this->existenciaAjena = $this->existencia(['id_comercio' => 3, 'id_variacion' => 30, 'id_ubicacion' => 1, 'cantidad_fisica' => 7]);
        config(['integraciones.m4.m3.doble' => CatalogoComercio3::class]);
        $this->reservaAjena = app(InventarioModuleInterface::class)->reservarExistencias(3, 30, 1)['id_reserva'];
    }

    public function test_un_comercio_no_ve_las_existencias_ni_el_kardex_de_otro(): void
    {
        $this->actingAs($this->encargado(1), 'sanctum');

        $this->getJson('/api/m4/v1/existencias?id_comercio=3')->assertNotFound()->assertJsonPath('error.codigo', 'ERR-003');
        $this->getJson('/api/m4/v1/kardex?id_comercio=3&id_variacion=30')->assertNotFound()->assertJsonPath('error.codigo', 'ERR-003');
        $this->getJson('/api/m4/v1/existencias?id_comercio=1')->assertOk()->assertJsonPath('paginacion.total', 1);
    }

    public function test_un_comercio_no_consulta_ni_modifica_reservas_ni_existencias_de_otro(): void
    {
        $this->actingAs($this->encargado(1), 'sanctum');

        $this->getJson("/api/m4/v1/reservas/{$this->reservaAjena}")->assertNotFound()->assertJsonPath('error.codigo', 'ERR-003');
        $this->postJson("/api/m4/v1/reservas/{$this->reservaAjena}/liberar", ['motivo' => 'solicitud_m5'])->assertNotFound();
        $this->putJson("/api/m4/v1/existencias/{$this->existenciaAjena}/stock-minimo", ['stock_minimo' => 99])->assertNotFound();

        $this->assertSame(0, (int) DB::table('existencias')->where('id_existencia', $this->existenciaAjena)->value('stock_minimo'));
        $this->assertSame(1, (int) DB::table('reservas')->where('id_reserva', $this->reservaAjena)->value('id_estado_reserva'));
    }

    public function test_un_comercio_no_transfiere_existencias_de_otro(): void
    {
        $this->actingAs($this->encargado(1), 'sanctum');

        $this->postJson('/api/m4/v1/transferencias', [
            'id_comercio' => 3, 'id_variacion' => 30, 'id_ubicacion_origen' => 1, 'id_ubicacion_destino' => 2,
            'cantidad' => 1, 'justificacion' => 'Intento ajeno', 'id_autorizacion' => 'autorizacion-ajena',
        ])->assertNotFound()->assertJsonPath('error.codigo', 'ERR-003');

        $this->assertSame(7, (int) DB::table('existencias')->where('id_existencia', $this->existenciaAjena)->value('cantidad_fisica'));
        $this->assertSame(0, DB::table('movimientos_inventario')->where('tipo', 'like', 'transferencia%')->count());
    }

    public function test_el_comercio_dueno_si_ve_sus_datos(): void
    {
        $this->actingAs($this->encargado(3), 'sanctum')
            ->getJson("/api/m4/v1/reservas/{$this->reservaAjena}")->assertOk()->assertJsonPath('id_comercio', 3);
    }

    public function test_la_plataforma_entra_a_varios_comercios_con_acceso_auditado(): void
    {
        // Prueba aparte: cada petición real es un proceso nuevo con su propio ámbito de comercio.
        $plataforma = new Usuario(['nombre' => 'Plataforma', 'correo' => 'plataforma@prueba.cr', 'rol' => 'encargado_plataforma', 'activo' => true]);
        $plataforma->setAttribute('id_usuario', 2);

        $this->actingAs($plataforma, 'sanctum')
            ->getJson('/api/m4/v1/auditoria/inventario?ids_comercio=1,3')
            ->assertOk()
            ->assertJsonCount(2, 'datos');
        $this->assertGreaterThan(0, DB::table('bitacora_auditoria')->where('accion', 'acceso_total_comercios')->count());
    }

    public function test_el_seeder_de_demostracion_es_idempotente_y_cuadra_con_el_kardex(): void
    {
        $this->seed(InventarioDemoSeeder::class);
        $this->seed(InventarioDemoSeeder::class);

        $this->assertSame(count(InventarioDemoSeeder::EXISTENCIAS) + 2, DB::table('existencias')->count());
        $fila = DB::table('existencias')->where(['id_comercio' => 1, 'id_variacion' => 501, 'id_ubicacion' => 1])->first();
        $this->assertSame(40, (int) $fila->cantidad_fisica);
        $this->assertSame(40, (int) DB::table('kardex_inventario as k')
            ->join('movimientos_inventario as m', 'm.id_movimiento', '=', 'k.id_movimiento')
            ->where(['m.id_comercio' => 1, 'm.id_variacion' => 501, 'm.id_ubicacion' => 1])
            ->value('k.saldo_cantidad'));
    }

    public function test_el_seeder_carga_las_variaciones_reales_del_catalogo_de_m3(): void
    {
        $this->seed(InventarioDemoSeeder::class);

        // variación => [comercio, producto, sede], como en producto_variaciones (M3) y sedes_comercio (M2).
        $esperado = [
            1 => [1, 1, 1], 2 => [1, 2, 1], 3 => [2, 3, 3], 4 => [3, 4, 4], 5 => [3, 5, 4], 6 => [4, 6, 5],
            7 => [4, 6, 5], 8 => [4, 7, 5], 9 => [5, 8, 6], 10 => [5, 9, 6], 11 => [5, 10, 6],
        ];

        foreach ($esperado as $variacion => [$comercio, $producto, $sede]) {
            $this->assertDatabaseHas('existencias', [
                'id_variacion' => $variacion, 'id_comercio' => $comercio, 'id_producto' => $producto, 'id_ubicacion' => $sede,
            ]);
        }

        $this->assertSame(30, (int) DB::table('existencias')
            ->where(['id_comercio' => 1, 'id_variacion' => 1, 'id_ubicacion' => 1])->value('cantidad_fisica'));
    }
}

/** Catálogo de prueba: las variaciones 30 a 39 son del comercio 3. */
class CatalogoComercio3 extends CatalogoStub
{
    public function obtenerVariacion(int $idVariacion): ?array
    {
        return ['id_comercio' => $idVariacion >= 30 && $idVariacion < 40 ? 3 : 1] + parent::obtenerVariacion($idVariacion);
    }
}
