<?php

namespace Tests\Feature\M1_Portal;

use App\Enums\Nucleo\RolUsuario;
use App\Models\Nucleo\Usuario;
use App\Services\M1_Portal\Dobles\DatosPrueba;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Doubles\M1\CatalogoNoDisponible;
use Tests\Feature\M1_Portal\Concerns\CreaTablasDeDestacados;
use Tests\Feature\M1_Portal\Concerns\CreaTablasDeNoticias;
use Tests\Feature\M1_Portal\Concerns\ValidaEsquemaContrato;

/**
 * Noticias de la plataforma (contrato M1 1.4.0, SC-08), por las rutas web con
 * los roles del núcleo:
 * - solo la administración (encargado_plataforma) las crea, edita, publica y archiva;
 * - el portal ve solo las publicadas y vigentes, de la más reciente a la más antigua;
 * - una noticia puede enlazar promociones, pero es una entidad aparte.
 */
class NoticiasTest extends PruebaM1
{
    use CreaTablasDeDestacados;
    use CreaTablasDeNoticias;
    use ValidaEsquemaContrato;

    private const AHORA = '2026-11-10 12:00:00';

    private const ADMINISTRACION = 9001;

    private const ENCARGADO_LA_CEIBA = 9002;

    private const CLIENTE = 9100;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararDestacados();
        $this->prepararNoticias();
        Carbon::setTestNow(self::AHORA);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Solo la administración publica
    // ---------------------------------------------------------------

    public function test_la_administracion_crea_una_noticia_en_borrador(): void
    {
        $respuesta = $this->crear()->assertCreated();

        $this->assertCumpleEsquema('Noticia', $respuesta->json());
        $respuesta->assertJsonPath('estado', 'borrador')
            ->assertJsonPath('slug', 'feria-navidena-en-liberia')
            ->assertJsonPath('fecha_publicacion', null)
            ->assertJsonPath('visible_en_portal', false)
            ->assertJsonPath('id_usuario_autor', self::ADMINISTRACION)
            ->assertJsonPath('fecha_fin', '2027-11-10T12:00:00+00:00');
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->postJson(route('api.m1.v1.administracion.noticias.crear'), $this->datos())->assertStatus(401);
        $this->getJson(route('api.m1.v1.administracion.noticias.listar'))->assertStatus(401);
    }

    public function test_un_comercio_no_publica_noticias_y_recibe_403(): void
    {
        $comercio = $this->cuentaConRol(self::ENCARGADO_LA_CEIBA, RolUsuario::EncargadoComercio, DatosPrueba::COMERCIO_LA_CEIBA);

        $this->como($comercio)->postJson(route('api.m1.v1.administracion.noticias.crear'), $this->datos())->assertStatus(403);

        $id = $this->crear()->json('id_noticia');

        $this->como($comercio)->postJson(route('api.m1.v1.administracion.noticias.publicar', $id))->assertStatus(403);
        $this->como($comercio)->postJson(route('api.m1.v1.administracion.noticias.archivar', $id))->assertStatus(403);
        $this->como($comercio)->putJson(route('api.m1.v1.administracion.noticias.actualizar', $id), $this->datos())->assertStatus(403);
        $this->como($comercio)->getJson(route('api.m1.v1.administracion.noticias.listar'))->assertStatus(403);
    }

    public function test_un_cliente_tampoco_publica_noticias(): void
    {
        $this->como($this->cuentaConRol(self::CLIENTE, RolUsuario::Cliente))
            ->postJson(route('api.m1.v1.administracion.noticias.crear'), $this->datos())
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Publicar, archivar y lo que ve el portal
    // ---------------------------------------------------------------

    public function test_al_publicar_la_noticia_aparece_en_el_portal(): void
    {
        $id = $this->crear()->json('id_noticia');
        $this->getJson(route('api.m1.v1.noticias'))->assertOk()->assertJsonPath('total', 0);

        $publicada = $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.publicar', $id))->assertOk();

        $this->assertCumpleEsquema('Noticia', $publicada->json());
        $publicada->assertJsonPath('estado', 'publicada')
            ->assertJsonPath('fecha_publicacion', '2026-11-10T12:00:00+00:00')
            ->assertJsonPath('visible_en_portal', true);

        $this->app['auth']->forgetGuards();
        $listado = $this->getJson(route('api.m1.v1.noticias'))->assertOk();
        $this->assertCumpleEsquema('ListadoNoticias', $listado->json());
        $listado->assertJsonPath('total', 1)->assertJsonPath('noticias.0.slug', 'feria-navidena-en-liberia');

        $detalle = $this->getJson(route('api.m1.v1.noticias.mostrar', 'feria-navidena-en-liberia'))->assertOk();
        $this->assertCumpleEsquema('NoticiaDetalle', $detalle->json());
        $detalle->assertJsonPath('contenido', 'Del 1 al 24 de diciembre, con comercios de toda la provincia.')
            ->assertJsonPath('promociones', []);
    }

    public function test_los_borradores_y_las_archivadas_no_se_ven_en_el_portal(): void
    {
        $this->crear(['titulo' => 'Borrador pendiente']);
        $archivada = $this->crear(['titulo' => 'Aviso viejo'])->json('id_noticia');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.publicar', $archivada))->assertOk();
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.archivar', $archivada))
            ->assertOk()->assertJsonPath('estado', 'archivada')->assertJsonPath('visible_en_portal', false);

        $this->app['auth']->forgetGuards();
        $this->getJson(route('api.m1.v1.noticias'))->assertOk()->assertJsonPath('total', 0)->assertJsonPath('noticias', []);
        $this->getJson(route('api.m1.v1.noticias.mostrar', 'borrador-pendiente'))->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
        $this->getJson(route('api.m1.v1.noticias.mostrar', 'aviso-viejo'))->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
    }

    public function test_el_portal_las_ordena_de_la_mas_reciente_a_la_mas_antigua_y_pagina(): void
    {
        foreach (['Primera' => '2026-11-01T08:00:00Z', 'Tercera' => '2026-11-09T08:00:00Z', 'Segunda' => '2026-11-05T08:00:00Z'] as $titulo => $fecha) {
            $id = $this->crear(['titulo' => $titulo])->json('id_noticia');
            $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.publicar', $id), ['fecha_publicacion' => $fecha])->assertOk();
        }

        $this->app['auth']->forgetGuards();
        $this->getJson(route('api.m1.v1.noticias'))->assertOk()
            ->assertJsonPath('noticias.*.titulo', ['Tercera', 'Segunda', 'Primera']);

        $this->getJson(route('api.m1.v1.noticias', ['pagina' => 2, 'por_pagina' => 2]))->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('pagina', 2)
            ->assertJsonPath('noticias.*.titulo', ['Primera']);
    }

    public function test_una_publicacion_programada_aparece_sola_y_una_vencida_deja_de_verse(): void
    {
        $id = $this->crear(['fecha_fin' => '2026-12-31T23:59:59Z'])->json('id_noticia');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.publicar', $id), ['fecha_publicacion' => '2026-12-01T00:00:00Z'])
            ->assertOk()->assertJsonPath('visible_en_portal', false);
        $this->app['auth']->forgetGuards();

        $this->getJson(route('api.m1.v1.noticias'))->assertJsonPath('total', 0);

        Carbon::setTestNow('2026-12-01 00:00:00');
        $this->getJson(route('api.m1.v1.noticias'))->assertJsonPath('total', 1);

        Carbon::setTestNow('2027-01-01 00:00:00');
        $this->getJson(route('api.m1.v1.noticias'))->assertJsonPath('total', 0);
        $this->getJson(route('api.m1.v1.noticias.mostrar', 'feria-navidena-en-liberia'))->assertNotFound();
    }

    public function test_las_fechas_con_hora_de_costa_rica_se_guardan_en_el_instante_correcto(): void
    {
        // AHORA es 12:00 UTC (6:00 en Costa Rica). Se publica a las 7:00 de Costa Rica, o sea 13:00 UTC.
        $id = $this->crear(['fecha_fin' => '2026-11-30T18:00:00-06:00'])
            ->assertCreated()
            ->assertJsonPath('fecha_fin', '2026-12-01T00:00:00+00:00')
            ->json('id_noticia');

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.publicar', $id), ['fecha_publicacion' => '2026-11-10T07:00:00-06:00'])
            ->assertOk()
            ->assertJsonPath('fecha_publicacion', '2026-11-10T13:00:00+00:00')
            ->assertJsonPath('visible_en_portal', false);
        $this->app['auth']->forgetGuards();

        Carbon::setTestNow('2026-11-10 13:00:00');
        $this->getJson(route('api.m1.v1.noticias'))->assertJsonPath('total', 1);
    }

    public function test_la_paginacion_invalida_responde_422(): void
    {
        $this->getJson(route('api.m1.v1.noticias', ['por_pagina' => 51, 'pagina' => 'abc']))
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonStructure(['error' => ['detalles' => ['pagina', 'por_pagina']]]);
    }

    // ---------------------------------------------------------------
    // Validaciones (422)
    // ---------------------------------------------------------------

    public function test_titulo_resumen_y_contenido_son_obligatorios(): void
    {
        $this->crear(['titulo' => '  ', 'resumen' => null, 'contenido' => ''])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonStructure(['error' => ['detalles' => ['titulo', 'resumen', 'contenido']]]);

        $this->crear(['resumen' => str_repeat('a', 301)])
            ->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['resumen']]]);
    }

    public function test_la_imagen_es_una_url_http_o_https(): void
    {
        $this->crear(['imagen_url' => 'no es una url'])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['imagen_url']]]);
        $this->crear(['imagen_url' => 'ftp://archivos.example/feria.jpg'])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['imagen_url']]]);
        $this->crear(['imagen_url' => null])->assertCreated()->assertJsonPath('imagen_url', null);
    }

    public function test_fecha_fin_debe_ser_futura_y_las_promociones_deben_existir(): void
    {
        $this->crear(['fecha_fin' => '2026-11-01T00:00:00Z'])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['fecha_fin']]]);
        $this->crear(['ids_promocion' => [999]])->assertStatus(422)->assertJsonPath('error.detalles.ids_promocion', 'No existe la promoción 999.');
        $this->crear(['ids_promocion' => 'uno'])->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['ids_promocion']]]);
    }

    public function test_el_estado_del_filtro_de_la_administracion_debe_ser_valido(): void
    {
        $this->crear();

        $this->comoAdministracion()->getJson(route('api.m1.v1.administracion.noticias.listar', ['estado' => 'borrador']))
            ->assertOk()->assertJsonCount(1);
        $this->comoAdministracion()->getJson(route('api.m1.v1.administracion.noticias.listar', ['estado' => 'publicada']))
            ->assertOk()->assertJsonCount(0);
        $this->comoAdministracion()->getJson(route('api.m1.v1.administracion.noticias.listar', ['estado' => 'otra']))
            ->assertStatus(422)->assertJsonStructure(['error' => ['detalles' => ['estado']]]);
    }

    // ---------------------------------------------------------------
    // Editar, slug y estados que no se modifican (409)
    // ---------------------------------------------------------------

    public function test_el_slug_es_unico_y_sigue_al_titulo_solo_mientras_es_borrador(): void
    {
        $this->crear()->assertJsonPath('slug', 'feria-navidena-en-liberia');
        $segunda = $this->crear()->assertJsonPath('slug', 'feria-navidena-en-liberia-2')->json('id_noticia');

        $this->comoAdministracion()->putJson(route('api.m1.v1.administracion.noticias.actualizar', $segunda), $this->datos(['titulo' => 'Mantenimiento programado']))
            ->assertOk()->assertJsonPath('slug', 'mantenimiento-programado');

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.publicar', $segunda))->assertOk();
        $this->comoAdministracion()->putJson(route('api.m1.v1.administracion.noticias.actualizar', $segunda), $this->datos(['titulo' => 'Mantenimiento del sábado']))
            ->assertOk()
            ->assertJsonPath('titulo', 'Mantenimiento del sábado')
            ->assertJsonPath('slug', 'mantenimiento-programado');
    }

    public function test_una_archivada_no_se_edita_ni_se_publica_ni_se_vuelve_a_archivar(): void
    {
        $id = $this->crear()->json('id_noticia');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.archivar', $id))->assertOk();

        $this->comoAdministracion()->putJson(route('api.m1.v1.administracion.noticias.actualizar', $id), $this->datos())
            ->assertStatus(409)->assertJsonPath('error.codigo', 'ERR-M1-006');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.publicar', $id))
            ->assertStatus(409)->assertJsonPath('error.codigo', 'ERR-M1-006');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.archivar', $id))
            ->assertStatus(409)->assertJsonPath('error.codigo', 'ERR-M1-006');
    }

    public function test_una_publicada_no_se_vuelve_a_publicar(): void
    {
        $id = $this->crear()->json('id_noticia');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.publicar', $id))->assertOk();

        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.publicar', $id))
            ->assertStatus(409)->assertJsonPath('error.codigo', 'ERR-M1-006')->assertJsonPath('error.mensaje', 'La noticia ya está publicada.');
    }

    public function test_una_noticia_inexistente_responde_404(): void
    {
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.publicar', 999))
            ->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
    }

    // ---------------------------------------------------------------
    // Enlace a promociones
    // ---------------------------------------------------------------

    public function test_el_detalle_muestra_solo_las_promociones_enlazadas_vigentes(): void
    {
        $vigente = $this->promocion('Semana de la camisa', '2026-11-01 00:00:00', '2026-11-30 23:59:59');
        $terminada = $this->promocion('Promo de octubre', '2026-10-01 00:00:00', '2026-10-31 23:59:59');

        $id = $this->crear(['ids_promocion' => [$vigente, $terminada, $vigente]])
            ->assertCreated()
            ->assertJsonPath('ids_promocion', [$vigente, $terminada])
            ->json('id_noticia');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.publicar', $id))->assertOk();

        $this->app['auth']->forgetGuards();
        $detalle = $this->getJson(route('api.m1.v1.noticias.mostrar', 'feria-navidena-en-liberia'))->assertOk();

        $this->assertCumpleEsquema('NoticiaDetalle', $detalle->json());
        $detalle->assertJsonCount(1, 'promociones')
            ->assertJsonPath('promociones.0.id_promocion', $vigente)
            ->assertJsonPath('promociones.0.producto.id_producto', DatosPrueba::PRODUCTO_CAMISA_LINO);
    }

    public function test_si_m3_no_responde_la_noticia_se_muestra_sin_promociones(): void
    {
        $vigente = $this->promocion('Semana de la camisa', '2026-11-01 00:00:00', '2026-11-30 23:59:59');
        $id = $this->crear(['ids_promocion' => [$vigente]])->json('id_noticia');
        $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.publicar', $id))->assertOk();
        config(['integraciones.m1.m3.doble' => CatalogoNoDisponible::class]);

        $this->app['auth']->forgetGuards();
        $this->getJson(route('api.m1.v1.noticias.mostrar', 'feria-navidena-en-liberia'))
            ->assertOk()
            ->assertJsonPath('titulo', 'Feria Navideña en Liberia')
            ->assertJsonPath('promociones', []);
    }

    // ---------------------------------------------------------------
    // Ayudantes
    // ---------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'titulo' => 'Feria Navideña en Liberia',
            'resumen' => 'Campaña de temporada con los comercios de Guanacaste.',
            'contenido' => 'Del 1 al 24 de diciembre, con comercios de toda la provincia.',
            'imagen_url' => 'https://cdn.pacificos.example/noticias/feria-navidena.jpg',
            ...$cambios,
        ];
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function crear(array $cambios = []): TestResponse
    {
        return $this->comoAdministracion()->postJson(route('api.m1.v1.administracion.noticias.crear'), $this->datos($cambios));
    }

    /**
     * Promoción de la Camisa de lino (1520, Tienda La Ceiba) para todo el producto.
     */
    private function promocion(string $titulo, string $inicio, string $fin): int
    {
        return DB::table('promociones')->insertGetId([
            'id_comercio' => DatosPrueba::COMERCIO_LA_CEIBA,
            'id_producto' => DatosPrueba::PRODUCTO_CAMISA_LINO,
            'titulo' => $titulo,
            'tipo' => 'porcentaje',
            'valor' => 20,
            'alcance' => 'producto',
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'activa' => true,
            'oculta' => false,
            'id_usuario_crea' => self::ENCARGADO_LA_CEIBA,
            'created_at' => self::AHORA,
            'updated_at' => self::AHORA,
        ], 'id_promocion');
    }

    private function comoAdministracion(): static
    {
        return $this->como($this->cuentaConRol(self::ADMINISTRACION, RolUsuario::EncargadoPlataforma));
    }

    /**
     * Cambia la persona con sesión. El guard de Sanctum recuerda al primer
     * usuario de la prueba, así que se olvidan los guards antes de cambiarlo.
     */
    private function como(Usuario $usuario): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($usuario, 'web');
    }
}
