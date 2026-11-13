<?php

declare(strict_types=1);

namespace Tests\Feature\M3_Catalogo\Eventos;

use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Events\M4_Inventario\ExistenciaRepuesta;
use App\Listeners\M3_Catalogo\MarcarVariacionAgotada;
use App\Services\M3_Catalogo\CatalogoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Tests\Unit\M3_Catalogo\Concerns\PreparaDatosConsultasM3;

/**
 * ExistenciaAgotada y ExistenciaRepuesta (M4 -> M3): efecto en la búsqueda, repetidos,
 * aislamiento por comercio (RN-12) y orden de llegada por fecha_evento.
 */
class M3_OrdenEventosExistenciaTest extends TestCase
{
    use PreparaDatosConsultasM3;

    private int $idProducto;

    private int $idVariacion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConsultasM3();

        $this->comercioActivo(1);
        $this->comercioActivo(2);
        $this->idProducto = $this->producto(1, $this->categoria('General'), 'Botella térmica');
        $this->idVariacion = $this->variacion($this->idProducto, 1, 'BOT-750', 10000);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** El evento toma su fecha_evento al crearse: se fija el reloj solo para construirlo. */
    private function agotada(string $hora, int $idComercio = 1): ExistenciaAgotada
    {
        Carbon::setTestNow("2026-11-13 {$hora}");
        $evento = new ExistenciaAgotada($idComercio, $this->idProducto, $this->idVariacion);
        Carbon::setTestNow();

        return $evento;
    }

    private function repuesta(string $hora, int $idComercio = 1): ExistenciaRepuesta
    {
        Carbon::setTestNow("2026-11-13 {$hora}");
        $evento = new ExistenciaRepuesta($idComercio, $this->idProducto, $this->idVariacion, 5);
        Carbon::setTestNow();

        return $evento;
    }

    private function fila(): object
    {
        return DB::table('producto_variaciones')->where('id_variacion', $this->idVariacion)->first();
    }

    private function disponible(): bool
    {
        return (bool) $this->fila()->disponible;
    }

    /** @return array<string, mixed> */
    private function productoEnBusqueda(): array
    {
        $resultado = app(CatalogoService::class)->buscarProductos();

        return collect($resultado['productos'])->firstWhere('id_producto', $this->idProducto);
    }

    // ------------------------------------------------------------ casos a) a d)

    public function test_agotada_quita_la_variacion_de_la_busqueda(): void
    {
        $this->variacion($this->idProducto, 1, 'BOT-1000', 20000);
        $this->assertSame(10000.0, $this->productoEnBusqueda()['precio_desde']);

        event($this->agotada('10:00:00'));

        $this->assertFalse($this->disponible());
        // La variación agotada deja de contar: el rango de precios sale solo de la que queda.
        $this->assertSame(20000.0, $this->productoEnBusqueda()['precio_desde']);
        $this->assertSame(20000.0, $this->productoEnBusqueda()['precio_hasta']);
    }

    public function test_producto_con_su_unica_variacion_agotada_sigue_listado_sin_precio(): void
    {
        event($this->agotada('10:00:00'));

        $producto = $this->productoEnBusqueda();

        $this->assertNull($producto['precio_desde']);
        $this->assertNull($producto['precio_hasta']);
    }

    public function test_repuesta_deja_la_variacion_disponible_y_vuelve_a_la_busqueda(): void
    {
        event($this->agotada('10:00:00'));
        event($this->repuesta('11:00:00'));

        $this->assertTrue($this->disponible());
        $this->assertSame(10000.0, $this->productoEnBusqueda()['precio_desde']);
    }

    public function test_el_mismo_evento_entregado_dos_veces_deja_el_mismo_resultado(): void
    {
        $evento = $this->agotada('10:00:00');

        event($evento);
        $primera = $this->fila();
        event($evento);
        $segunda = $this->fila();

        $this->assertFalse((bool) $segunda->disponible);
        $this->assertSame($evento->idEvento, $segunda->id_ultimo_evento);
        $this->assertSame($primera->fecha_ultimo_evento, $segunda->fecha_ultimo_evento);
    }

    public function test_evento_de_otro_comercio_no_cambia_nada_rn12(): void
    {
        event($this->agotada('10:00:00', 2));

        $fila = $this->fila();
        $this->assertTrue((bool) $fila->disponible);
        $this->assertNull($fila->id_ultimo_evento);
        $this->assertNull($fila->fecha_ultimo_evento);
    }

    // ------------------------------------------------------- orden de llegada

    public function test_repuesta_vieja_despues_de_agotada_no_se_aplica(): void
    {
        $agotada = $this->agotada('12:00:00');
        $repuestaVieja = $this->repuesta('09:00:00');

        event($agotada);
        event($repuestaVieja);

        $this->assertFalse($this->disponible());
        $this->assertSame($agotada->idEvento, $this->fila()->id_ultimo_evento);
    }

    public function test_agotada_vieja_despues_de_repuesta_no_se_aplica(): void
    {
        $repuesta = $this->repuesta('12:00:00');
        $agotadaVieja = $this->agotada('09:00:00');

        event($repuesta);
        event($agotadaVieja);

        $this->assertTrue($this->disponible());
        $this->assertSame($repuesta->idEvento, $this->fila()->id_ultimo_evento);
    }

    public function test_el_orden_correcto_si_se_aplica_y_guarda_el_ultimo_evento(): void
    {
        $agotada = $this->agotada('09:00:00');
        $repuesta = $this->repuesta('12:00:00');

        event($agotada);
        $this->assertFalse($this->disponible());

        event($repuesta);

        $fila = $this->fila();
        $this->assertTrue((bool) $fila->disponible);
        $this->assertSame($repuesta->idEvento, $fila->id_ultimo_evento);
        $this->assertTrue(Carbon::parse($fila->fecha_ultimo_evento)->equalTo(Carbon::parse($repuesta->fechaEvento)));
    }

    public function test_dos_eventos_distintos_con_la_misma_fecha_se_aplican_en_orden_de_llegada(): void
    {
        event($this->agotada('10:00:00'));
        event($this->repuesta('10:00:00'));

        $this->assertTrue($this->disponible());
    }

    public function test_un_evento_viejo_descartado_no_genera_error(): void
    {
        Log::spy();

        event($this->agotada('12:00:00'));
        event($this->repuesta('09:00:00'));

        Log::shouldNotHaveReceived('error');
    }

    public function test_evento_sin_fecha_se_aplica_como_antes(): void
    {
        // Los eventos reales de M4 siempre traen fecha; se entrega un objeto simple
        // directo al listener de M3 (el de M1 solo acepta la clase del evento).
        $sinFecha = (object) ['id_evento' => 'sin-fecha', 'id_comercio' => 1, 'id_variacion' => $this->idVariacion];

        event($this->repuesta('12:00:00'));
        app(MarcarVariacionAgotada::class)->handle($sinFecha);

        $fila = $this->fila();
        $this->assertFalse((bool) $fila->disponible);
        // No toca las marcas: el último evento con fecha sigue siendo la referencia.
        $this->assertNotSame('sin-fecha', $fila->id_ultimo_evento);
        $this->assertNotNull($fila->fecha_ultimo_evento);
    }

    public function test_sin_las_columnas_nuevas_el_evento_se_aplica_como_antes(): void
    {
        // Base donde todavía no corrió la migración de fecha_ultimo_evento.
        DB::statement('ALTER TABLE producto_variaciones DROP COLUMN fecha_ultimo_evento');
        DB::statement('ALTER TABLE producto_variaciones DROP COLUMN id_ultimo_evento');

        event($this->agotada('10:00:00'));

        $this->assertFalse($this->disponible());
    }
}
