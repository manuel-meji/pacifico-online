<?php

namespace Tests\Feature\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Models\Nucleo\Usuario;
use App\Services\M4_Inventario\Dobles\CatalogoStub;
use App\Services\M4_Inventario\Dobles\ComerciosStub;
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

    public function test_el_seeder_carga_el_catalogo_de_m3_y_la_tabla_5_en_las_sedes_que_da_m2(): void
    {
        config(['integraciones.m4.m2.doble' => SedesComerciosEscenario::class]);

        $this->seed(InventarioDemoSeeder::class);

        // variación => [comercio, producto, sede, existencia, costo], como en M3CatalogoSeeder y la tabla 5 del escenario 2.2.
        $esperado = [
            1 => [1, 1, 1, 30, 6500], 2 => [1, 2, 1, 20, 9750], 3 => [2, 3, 3, 30, 2900], 4 => [2, 4, 3, 15, 2300],
            5 => [3, 5, 4, 20, 16000], 6 => [3, 6, 4, 20, 19500],
            7 => [4, 7, 5, 6, 4200], 8 => [4, 7, 5, 2, 4600], 9 => [4, 8, 5, 1, 3100],
            10 => [5, 9, 6, 12, 2900], 11 => [5, 10, 6, 8, 1400],
        ];

        foreach ($esperado as $variacion => [$comercio, $producto, $sede, $cantidad, $costo]) {
            $fila = DB::table('existencias')->where(['id_variacion' => $variacion, 'id_comercio' => $comercio])->first();
            $this->assertNotNull($fila, "Falta la variación {$variacion}.");
            $this->assertSame([$producto, $sede, $cantidad], [(int) $fila->id_producto, (int) $fila->id_ubicacion, (int) $fila->cantidad_fisica], "Variación {$variacion}.");
            $this->assertEqualsWithDelta($costo, (float) $fila->costo_promedio, 0.001, "Costo de la variación {$variacion}.");
        }

        // La 501 queda en la sede principal y en la segunda sede del comercio 1.
        $this->assertSame([1, 2], DB::table('existencias')->where(['id_comercio' => 1, 'id_variacion' => 501])
            ->orderBy('id_ubicacion')->pluck('id_ubicacion')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_el_seeder_se_detiene_si_m2_no_tiene_las_sedes(): void
    {
        config(['integraciones.m4.m2.doble' => SedesComerciosEscenario::class]);
        SedesComerciosEscenario::$sinSedes = [4];

        try {
            $this->seed(InventarioDemoSeeder::class);
            $this->fail('El seeder debía detenerse.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('comercio 4', $e->getMessage());
        } finally {
            SedesComerciosEscenario::$sinSedes = [];
        }

        $this->assertSame(2, DB::table('existencias')->count(), 'No escribe nada si falta una sede.');
    }
}

/** Sedes como las crea ComerciosDemoSeeder de M2: comercio 1 → 1 y 2, comercio 2 → 3, 3 → 4, 4 → 5, 5 → 6. */
class SedesComerciosEscenario extends ComerciosStub
{
    /** @var list<int> */
    public static array $sinSedes = [];

    public function obtenerSedesComercio(int $idComercio): array
    {
        $ids = in_array($idComercio, self::$sinSedes, true) ? [] : match ($idComercio) {
            1 => [2, 1],
            2 => [3],
            3 => [4],
            4 => [5],
            5 => [6],
            default => [],
        };

        return ['id_comercio' => $idComercio, 'sedes' => array_map(fn (int $id): array => ['id_sede' => $id, 'habilitada' => true], $ids)];
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
