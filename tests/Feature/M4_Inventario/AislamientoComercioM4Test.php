<?php

namespace Tests\Feature\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Models\M4_Inventario\Existencia;
use App\Services\M4_Inventario\Dependencias\NucleoReal;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * RN-12 con el núcleo real: el aislamiento por comercio lo aplica el Global Scope
 * del núcleo (PerteneceAComercio sobre los modelos de M4) con el comercio que deja
 * en ContextoComercio el middleware EstablecerComercio. M4 no agrega un filtro
 * opcional: sin ámbito, el scope no devuelve nada (falla cerrado).
 */
class AislamientoComercioM4Test extends TestCase
{
    use EsquemaInventarioPrueba;

    protected function setUp(): void
    {
        parent::setUp();

        config(['integraciones.m4.nucleo.modo' => 'real']);
        $this->crearEsquemaInventario();

        $this->existencia(['id_comercio' => 2, 'id_variacion' => 20, 'id_ubicacion' => 1, 'cantidad_fisica' => 5]);
        $this->existencia(['id_comercio' => 3, 'id_variacion' => 30, 'id_ubicacion' => 1, 'cantidad_fisica' => 7]);
    }

    public function test_m4_usa_el_adaptador_real_del_nucleo(): void
    {
        $this->assertInstanceOf(NucleoReal::class, app(NucleoDependencia::class));
    }

    public function test_con_sesion_de_un_comercio_el_scope_del_nucleo_solo_devuelve_sus_existencias(): void
    {
        app(ContextoComercio::class)->establecer(2);

        $this->assertSame([2], Existencia::query()->pluck('id_comercio')->unique()->values()->all());
        $this->assertNull(Existencia::query()->where('id_comercio', 3)->first());
    }

    public function test_sin_ambito_el_scope_falla_cerrado(): void
    {
        $this->assertSame(0, Existencia::query()->count());
    }

    public function test_una_llamada_interna_sin_sesion_corre_en_modo_sistema_y_filtra_por_el_id_del_contrato(): void
    {
        $resultado = app(InventarioModuleInterface::class)->consultarStockDisponible(3, 30);

        $this->assertSame(7, $resultado['cantidad_disponible']);
    }

    public function test_con_sesion_de_un_comercio_no_se_libera_una_reserva_de_otro(): void
    {
        $idReserva = $this->reserva(comercio: 3);
        app(ContextoComercio::class)->establecer(2);

        try {
            app(InventarioModuleInterface::class)->liberarReserva($idReserva, 'solicitud_m5');
            $this->fail('Debió responder ERR-003.');
        } catch (ErrorInventarioException $e) {
            $this->assertSame('ERR-003', $e->codigo);
            $this->assertSame(404, $e->http);
        }

        $this->assertSame(1, (int) DB::table('reservas')->where('id_reserva', $idReserva)->value('id_estado_reserva'));
    }

    public function test_con_sesion_de_un_comercio_no_se_escribe_en_otro(): void
    {
        app(ContextoComercio::class)->establecer(2);

        try {
            app(NucleoDependencia::class)->ejecutarEnAmbito(fn () => Existencia::query()->create([
                'id_comercio' => 3, 'id_producto' => 1, 'id_variacion' => 31, 'id_ubicacion' => 1,
            ]));
            $this->fail('Debió responder ERR-003.');
        } catch (ErrorInventarioException $e) {
            $this->assertSame('ERR-003', $e->codigo);
        }

        $this->assertSame(0, DB::table('existencias')->where('id_variacion', 31)->count());
    }

    private function reserva(int $comercio): string
    {
        $id = (string) Str::uuid();
        $ahora = Carbon::now();

        DB::table('reservas')->insert([
            'id_reserva' => $id,
            'id_comercio' => $comercio,
            'id_existencia' => DB::table('existencias')->where('id_comercio', $comercio)->value('id_existencia'),
            'id_estado_reserva' => 1,
            'cantidad' => 1,
            'fecha_creacion' => $ahora,
            'fecha_vencimiento' => $ahora->copy()->addMinutes(15),
            'clave_idempotencia' => (string) Str::uuid(),
            'historial' => '[]',
        ]);

        return $id;
    }
}
