<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M1_Portal\DTO\ComercioDTO;
use App\Services\M1_Portal\MicrositioService;
use Tests\Doubles\M1\ComerciosNoDisponible;
use Tests\Feature\M1_Portal\Concerns\ValidaEsquemaContrato;

/**
 * Búsqueda de comercios por nombre (contrato 1.6.0, SC-13): parámetro q de
 * GET /comercios. M1 filtra el listado de habilitados de M2 (doble
 * ComerciosStub: Tienda La Ceiba, Café Nicoyano, Artesanías Guaitil y la
 * suspendida Ferretería El Pochote).
 */
class BusquedaComerciosTest extends PruebaM1
{
    use ValidaEsquemaContrato;

    public function test_busca_por_nombre_sin_distinguir_mayusculas_ni_tildes(): void
    {
        $listado = $this->servicio()->buscarComercios('CAFE', 1, 20);

        $this->assertSame([DatosPrueba::COMERCIO_CAFE_NICOYANO], $this->ids($listado->comercios));
        $this->assertSame(1, $listado->total);

        $listado = $this->servicio()->buscarComercios('artesanias', 1, 20);

        $this->assertSame([DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL], $this->ids($listado->comercios));
    }

    public function test_primero_van_los_que_empiezan_con_el_termino(): void
    {
        // "ti" está en "Tienda La Ceiba" (al inicio) y en "Artesanías Guaitil" (en medio).
        $listado = $this->servicio()->buscarComercios('ti', 1, 20);

        $this->assertSame([DatosPrueba::COMERCIO_LA_CEIBA, DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL], $this->ids($listado->comercios));
    }

    public function test_no_incluye_comercios_suspendidos(): void
    {
        $listado = $this->servicio()->buscarComercios('pochote', 1, 20);

        $this->assertSame([], $listado->comercios);
        $this->assertSame(0, $listado->total);
    }

    public function test_pagina_las_coincidencias(): void
    {
        $listado = $this->servicio()->buscarComercios('ti', 2, 1);

        $this->assertSame([DatosPrueba::COMERCIO_ARTESANIAS_GUAITIL], $this->ids($listado->comercios));
        $this->assertSame(2, $listado->total);
        $this->assertSame(2, $listado->pagina);
    }

    public function test_un_termino_muy_corto_o_muy_largo_lanza_err_005(): void
    {
        foreach (['c', ' c ', str_repeat('a', 101)] as $termino) {
            try {
                $this->servicio()->buscarComercios($termino, 1, 20);
                $this->fail('Debió rechazar el término de largo '.mb_strlen($termino).'.');
            } catch (DatosInvalidosException $error) {
                $this->assertArrayHasKey('q', $error->detalles());
            }
        }
    }

    public function test_si_m2_no_responde_lanza_err_006(): void
    {
        config(['integraciones.m1.m2.doble' => ComerciosNoDisponible::class]);

        $this->expectException(DependenciaNoDisponibleException::class);

        $this->servicio()->buscarComercios('cafe', 1, 20);
    }

    public function test_get_comercios_con_q_responde_el_esquema_listado_comercios(): void
    {
        $respuesta = $this->getJson(route('api.m1.v1.comercios', ['q' => 'ceiba']))->assertOk();

        $this->assertCumpleEsquema('ListadoComercios', $respuesta->json());
        $respuesta->assertJson(['pagina' => 1, 'por_pagina' => 20, 'total' => 1])
            ->assertJsonPath('comercios.0.id_comercio', DatosPrueba::COMERCIO_LA_CEIBA);
    }

    public function test_get_comercios_con_q_vacio_lista_todos_como_antes(): void
    {
        $this->getJson(route('api.m1.v1.comercios', ['q' => '  ']))
            ->assertOk()
            ->assertJsonPath('total', 3);
    }

    public function test_get_comercios_con_q_de_un_caracter_responde_422_err_005(): void
    {
        $this->getJson(route('api.m1.v1.comercios', ['q' => 'c']))
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonStructure(['error' => ['detalles' => ['q']]]);
    }

    private function servicio(): MicrositioService
    {
        return app(MicrositioService::class);
    }

    /**
     * @param  list<ComercioDTO>  $comercios
     * @return list<int>
     */
    private function ids(array $comercios): array
    {
        return array_map(fn ($comercio): int => $comercio->idComercio, $comercios);
    }
}
