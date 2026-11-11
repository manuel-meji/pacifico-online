<?php

namespace Tests\Feature\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Services\M4_Inventario\Dependencias\NucleoReal;
use App\Services\M4_Inventario\Dobles\NucleoStub;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\M4_Inventario\InventarioService;
use App\Services\Nucleo\ContextoComercio;
use ReflectionMethod;
use Tests\TestCase;

/**
 * M4 con el núcleo real: el comercio de la sesión sale de ContextoComercio (RN-12)
 * y el servicio rechaza con ERR-003 los recursos de otro comercio.
 */
class NucleoRealM4Test extends TestCase
{
    public function test_en_modo_real_m4_lee_el_comercio_del_contexto_del_nucleo(): void
    {
        config(['integraciones.m4.nucleo.modo' => 'real']);

        $nucleo = app(NucleoDependencia::class);

        $this->assertInstanceOf(NucleoReal::class, $nucleo);
        $this->assertNull($nucleo->comercioActual(), 'Sin comercio en el ámbito no debe haber comercio por defecto.');

        app(ContextoComercio::class)->establecer(7);

        $this->assertSame(7, app(NucleoDependencia::class)->comercioActual());
    }

    public function test_la_guarda_deja_pasar_el_propio_comercio(): void
    {
        $this->assertNull($this->guarda(comercioSesion: 2, comercioRecurso: 2));
    }

    public function test_la_guarda_deja_pasar_sin_sesion_de_comercio(): void
    {
        $this->assertNull($this->guarda(comercioSesion: null, comercioRecurso: 5));
    }

    public function test_la_guarda_rechaza_con_err_003_un_recurso_de_otro_comercio(): void
    {
        $this->expectException(ErrorInventarioException::class);

        $this->guarda(comercioSesion: 2, comercioRecurso: 3);
    }

    private function guarda(?int $comercioSesion, int $comercioRecurso): mixed
    {
        $this->app->instance(NucleoDependencia::class, new NucleoStub($comercioSesion));

        $metodo = new ReflectionMethod(InventarioService::class, 'exigirComercioDeSesion');

        return $metodo->invoke(app(InventarioService::class), $comercioRecurso);
    }
}
