<?php

namespace Tests\Unit\M1_Portal;

use App\Services\M1_Portal\DTO\Disponibilidad;
use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use App\Services\M1_Portal\ReglasDestacados;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Reglas de la portada para productos destacados (contrato M1 1.4.0, SC-08):
 * máximo 3 por comercio y rotación por turnos de 15 minutos.
 */
class ReglasDestacadosTest extends TestCase
{
    private ReglasDestacados $reglas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reglas = new ReglasDestacados;
    }

    public function test_cada_comercio_muestra_como_maximo_3_productos_en_el_orden_de_posicion(): void
    {
        $productos = [
            $this->producto(1, comercio: 10),
            $this->producto(2, comercio: 10),
            $this->producto(3, comercio: 20),
            $this->producto(4, comercio: 10),
            $this->producto(5, comercio: 10),
            $this->producto(6, comercio: 20),
        ];

        $ids = array_map(fn (ProductoResumenDTO $p): int => $p->idProducto, $this->reglas->limitarPorComercio($productos));

        $this->assertSame([1, 2, 3, 4, 6], $ids);
        $this->assertSame(3, ReglasDestacados::MAXIMO_POR_COMERCIO);
    }

    public function test_si_caben_todos_no_se_rotan(): void
    {
        $lista = range(1, 12);

        $this->assertSame($lista, $this->reglas->rotar($lista, 12, Carbon::parse('2026-11-10 12:07:00')));
    }

    public function test_con_mas_del_limite_cada_turno_de_15_minutos_avanza_el_limite_de_posiciones(): void
    {
        $lista = range(1, 30);
        $inicioDeTurno = Carbon::createFromTimestamp(intdiv(Carbon::parse('2026-11-10 12:00:00')->getTimestamp(), 900) * 900);
        $turno = $this->reglas->turno($inicioDeTurno);
        $esperadoInicio = ($turno * 12) % 30;

        $primero = $this->reglas->rotar($lista, 12, $inicioDeTurno);
        $mismoTurno = $this->reglas->rotar($lista, 12, $inicioDeTurno->copy()->addMinutes(14)->addSeconds(59));
        $siguiente = $this->reglas->rotar($lista, 12, $inicioDeTurno->copy()->addMinutes(15));

        $this->assertCount(12, $primero);
        $this->assertSame($lista[$esperadoInicio], $primero[0]);
        $this->assertSame($primero, $mismoTurno, 'Dentro del mismo turno todas las personas ven lo mismo.');
        $this->assertSame($lista[($esperadoInicio + 12) % 30], $siguiente[0]);
    }

    public function test_la_rotacion_toma_la_lista_como_circular_y_no_repite_dentro_de_un_turno(): void
    {
        $lista = range(1, 13);
        $ahora = Carbon::parse('2026-11-10 12:00:00');

        $mostrados = $this->reglas->rotar($lista, 12, $ahora);

        $this->assertCount(12, $mostrados);
        $this->assertCount(12, array_unique($mostrados));
    }

    public function test_en_varios_turnos_todos_los_destacados_aparecen_el_mismo_numero_de_veces(): void
    {
        $lista = range(1, 18);
        $veces = array_fill_keys($lista, 0);
        $inicio = Carbon::createFromTimestamp(intdiv(Carbon::parse('2026-11-10 00:00:00')->getTimestamp(), 900) * 900);

        // 18 elementos y 12 por turno: cada 3 turnos se completa un ciclo exacto.
        for ($i = 0; $i < 3; $i++) {
            foreach ($this->reglas->rotar($lista, 12, $inicio->copy()->addMinutes(15 * $i)) as $id) {
                $veces[$id]++;
            }
        }

        $this->assertSame([2], array_values(array_unique($veces)));
    }

    private function producto(int $id, int $comercio): ProductoResumenDTO
    {
        return new ProductoResumenDTO(
            idProducto: $id,
            idComercio: $comercio,
            nombre: 'Producto '.$id,
            imagenPrincipal: null,
            precio: 1000.0,
            precioHasta: 1000.0,
            moneda: 'CRC',
            nombreComercio: 'Comercio '.$comercio,
            disponibilidad: Disponibilidad::Disponible,
        );
    }
}
