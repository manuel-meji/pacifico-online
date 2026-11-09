<?php

declare(strict_types=1);

namespace Tests\Unit\M2_Comercios;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\ComisionComercio;
use App\Models\M2_Comercios\ComisionSubpedido;
use App\Models\M2_Comercios\HorarioComercio;
use App\Models\M2_Comercios\PublicidadComercio;
use App\Services\M2_Comercios\ComerciosService;
use App\Services\M2_Comercios\Dobles\ComerciosDobleBase;
use Closure;
use Database\Seeders\M2_Comercios\ComerciosDemoSeeder;
use Illuminate\Support\Carbon;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/** Pruebas unitarias de la fachada de M2 (ComerciosService), contrato v1.2.1. */
class ComerciosServiceTest extends TestCase
{
    use PreparaM2;

    private ComerciosService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-15 10:00:00');
        $this->servicio = app(ComerciosService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_listar_solo_devuelve_comercios_activos_paginados(): void
    {
        $this->crearComercio(['nombre_comercial' => 'A']);
        $this->crearComercio(['nombre_comercial' => 'B']);
        $this->crearComercio(['nombre_comercial' => 'C']);
        $this->crearComercio(['estado' => Comercio::PENDIENTE]);
        $this->crearComercio(['estado' => Comercio::SUSPENDIDO]);

        $pagina = $this->servicio->listarComerciosHabilitados(2, 2);

        $this->assertSame(3, $pagina['total']);
        $this->assertSame(['C'], array_column($pagina['comercios'], 'nombre_comercial'));
        $this->assertSame('Activo', $pagina['comercios'][0]['estado']);
    }

    public function test_destacados_solo_con_publicidad_vigente_y_la_mas_reciente_primero(): void
    {
        $antigua = $this->crearComercio(['nombre_comercial' => 'Antigua']);
        $reciente = $this->crearComercio(['nombre_comercial' => 'Reciente']);
        $vencida = $this->crearComercio(['nombre_comercial' => 'Vencida']);
        $antigua->publicidades()->create(['ubicacion' => 'portada', 'inicio' => '2026-11-01', 'fin' => '2026-11-30']);
        $reciente->publicidades()->create(['ubicacion' => 'portada', 'inicio' => '2026-11-10', 'fin' => '2026-11-30']);
        $vencida->publicidades()->create(['ubicacion' => 'portada', 'inicio' => '2026-10-01', 'fin' => '2026-10-31']);

        $pagina = $this->servicio->listarComerciosHabilitados(1, 20, true);

        $this->assertSame(['Reciente', 'Antigua'], array_column($pagina['comercios'], 'nombre_comercial'));
        $this->assertTrue($pagina['comercios'][0]['destacado']);
    }

    public function test_listar_con_por_pagina_mayor_a_50_lanza_err_005(): void
    {
        $this->esperarError('ERR-005', 422, fn () => $this->servicio->listarComerciosHabilitados(1, 51));
    }

    public function test_lote_omite_no_habilitados_y_conserva_el_orden(): void
    {
        $a = $this->crearComercio();
        $b = $this->crearComercio();
        $suspendido = $this->crearComercio(['estado' => Comercio::SUSPENDIDO]);

        $lote = $this->servicio->obtenerResumenComerciosLote([$b->id_comercio, $suspendido->id_comercio, 999, $a->id_comercio]);

        $this->assertSame([$b->id_comercio, $a->id_comercio], array_column($lote['comercios'], 'id_comercio'));
    }

    public function test_lote_vacio_mayor_a_50_o_repetido_lanza_err_005(): void
    {
        $this->esperarError('ERR-005', 422, fn () => $this->servicio->obtenerResumenComerciosLote([]));
        $this->esperarError('ERR-005', 422, fn () => $this->servicio->obtenerResumenComerciosLote(range(1, 51)));
        $this->esperarError('ERR-005', 422, fn () => $this->servicio->obtenerResumenComerciosLote([1, 1]));
    }

    public function test_micrositio_publico_arma_contacto_redes_horarios_y_banners_activos(): void
    {
        $c = $this->crearComercio();
        $this->comoSistema(function () use ($c): void {
            $c->micrositio()->create(['descripcion' => 'Soda típica', 'logotipo' => 'https://cdn.test/logo.png']);
            $c->contactos()->create(['tipo' => 'telefono', 'valor' => '2666-0000']);
            $c->contactos()->create(['tipo' => 'instagram', 'valor' => 'https://instagram.com/soda']);
            $c->horarios()->create(['dia_semana' => 1, 'hora_apertura' => '08:00:00', 'hora_cierre' => '17:30:00', 'cerrado' => false]);
            $c->banners()->create(['ruta' => 'https://cdn.test/b1.png', 'texto_alternativo' => 'Activo', 'orden' => 1, 'activo' => true]);
            $c->banners()->create(['ruta' => 'https://cdn.test/b2.png', 'texto_alternativo' => 'Inactivo', 'orden' => 2, 'activo' => false]);
        });

        $datos = $this->servicio->obtenerDatosComercioPublico($c->id_comercio);

        $this->assertSame('Soda típica', $datos['descripcion']);
        $this->assertSame('https://cdn.test/logo.png', $datos['logo_url']);
        $this->assertSame(['telefono' => '2666-0000', 'correo' => null, 'direccion' => null], $datos['contacto']);
        $this->assertSame([['plataforma' => 'instagram', 'url' => 'https://instagram.com/soda']], $datos['redes_sociales']);
        $this->assertSame('08:00', $datos['horarios'][0]['hora_apertura']);
        $this->assertSame('17:30', $datos['horarios'][0]['hora_cierre']);
        $this->assertCount(1, $datos['banners']);
    }

    public function test_micrositio_de_comercio_no_habilitado_lanza_err_004(): void
    {
        $pendiente = $this->crearComercio(['estado' => Comercio::PENDIENTE]);

        $this->esperarError('ERR-004', 404, fn () => $this->servicio->obtenerDatosComercioPublico($pendiente->id_comercio));
    }

    public function test_estado_de_comercio_suspendido_devuelve_el_motivo_de_la_suspension_abierta(): void
    {
        $c = $this->crearComercio(['estado' => Comercio::SUSPENDIDO]);
        $c->suspensiones()->create(['id_usuario' => 1, 'motivo' => 'Motivo viejo', 'fecha_suspension' => '2026-10-01', 'fecha_reactivacion' => '2026-10-05']);
        $c->suspensiones()->create(['id_usuario' => 1, 'motivo' => 'Documentos vencidos', 'fecha_suspension' => '2026-11-10']);

        $this->assertSame(
            ['id_comercio' => $c->id_comercio, 'estado' => 'Suspendido', 'activo' => false, 'motivo_estado' => 'Documentos vencidos'],
            $this->servicio->obtenerComercioActivo($c->id_comercio),
        );
    }

    public function test_estado_de_comercio_rechazado_devuelve_el_motivo_del_rechazo(): void
    {
        $c = $this->crearComercio(['estado' => Comercio::RECHAZADO]);
        $c->solicitudes()->create([
            'nombre_encargado' => 'Ana', 'correo_encargado' => 'ana@test.cr',
            'estado' => 'Rechazada', 'motivo_rechazo' => 'No es contribuyente',
        ]);

        $this->assertSame('No es contribuyente', $this->servicio->obtenerComercioActivo($c->id_comercio)['motivo_estado']);
        $this->esperarError('ERR-004', 404, fn () => $this->servicio->obtenerComercioActivo(999));
    }

    public function test_datos_fiscales_usan_la_comision_vigente_y_no_una_futura(): void
    {
        $c = $this->crearComercio();
        $this->crearComision($c, 'porcentaje', 8, null, '2026-11-01 00:00:00');
        $this->crearComision($c, 'monto_fijo', 1500, 'CRC', '2026-12-01 00:00:00');

        $datos = $this->servicio->obtenerDatosFiscalesComercio($c->id_comercio);

        $this->assertSame($c->cedula_juridica, $datos['cedula_juridica']);
        $this->assertTrue($datos['inscrito_como_contribuyente']);
        $this->assertSame(['tipo' => 'porcentaje', 'valor' => 8.0, 'base_calculo' => 'subtotal del subpedido', 'moneda' => null], $datos['comision']);
    }

    public function test_politicas_de_envio_incluyen_las_tarifas(): void
    {
        $c = $this->crearComercio();
        $this->comoSistema(function () use ($c): void {
            $c->politicas()->create(['tipo' => 'envio', 'contenido' => 'Envío nacional', 'configuracion_envio' => [['zona' => 'GAM', 'costo' => 2500, 'moneda' => 'CRC']]]);
            $c->politicas()->create(['tipo' => 'devolucion', 'contenido' => '8 días']);
        });

        $datos = $this->servicio->obtenerPoliticasEnvio($c->id_comercio);

        $this->assertCount(2, $datos['politicas']);
        $this->assertSame([['zona' => 'GAM', 'costo' => 2500.0, 'moneda' => 'CRC']], $datos['tarifas']);
    }

    public function test_comision_de_subpedido_calculada_y_no_calculada(): void
    {
        $c = $this->crearComercio();
        ComisionSubpedido::query()->create([
            'id_subpedido' => 7001, 'id_comercio' => $c->id_comercio, 'subtotal_base' => 24500,
            'monto' => 1960, 'moneda' => 'CRC', 'id_evento' => '6b1f5c0e-2f0a-4b8e-9d55-0d6a0c1c7a01',
        ]);

        $this->assertSame(
            ['id_subpedido' => 7001, 'id_comercio' => $c->id_comercio, 'monto_comision' => 1960.0, 'base_utilizada' => 24500.0, 'moneda' => 'CRC'],
            $this->servicio->obtenerComisionSubpedido(7001),
        );
        $this->assertTrue(ComisionSubpedido::existePorSubpedido(7001));
        $this->esperarError('ERR-004', 404, fn () => $this->servicio->obtenerComisionSubpedido(7002));
    }

    public function test_seeder_crea_los_cinco_comercios_del_escenario_y_es_idempotente(): void
    {
        $this->seed(ComerciosDemoSeeder::class);
        $this->seed(ComerciosDemoSeeder::class);

        $this->assertSame(5, Comercio::query()->habilitados()->count());
        $esperado = ['3-101-100001' => 8.0, '3-101-100002' => 1000.0, '3-101-100003' => 10.0, '3-101-100004' => 9.0, '3-101-100005' => 7.0];
        foreach ($esperado as $cedula => $valor) {
            $id = Comercio::query()->where('cedula_juridica', $cedula)->value('id_comercio');
            $this->assertSame($valor, $this->servicio->obtenerDatosFiscalesComercio($id)['comision']['valor']);
        }
        $this->assertSame(5, ComisionComercio::query()->count());
        $this->assertSame(35, $this->comoSistema(fn () => HorarioComercio::query()->count()));
        $this->assertSame(2, PublicidadComercio::query()->count());
        $this->assertSame(2, $this->servicio->listarComerciosHabilitados(1, 20, true)['total']);
    }

    public function test_doble_base_responde_err_006_en_operaciones_no_simuladas(): void
    {
        $doble = new class extends ComerciosDobleBase
        {
            public function obtenerMicrositio(int $comercioId): array
            {
                return [];
            }

            public function registrarComision(int $pedidoId, float $subtotalPedido): bool
            {
                return false;
            }
        };

        $this->esperarError('ERR-006', 503, fn () => $doble->obtenerComercioActivo(1));
    }

    private function esperarError(string $codigo, int $http, Closure $operacion): void
    {
        try {
            $operacion();
            $this->fail("Se esperaba {$codigo}.");
        } catch (ErrorNucleoException $e) {
            $this->assertSame($codigo, $e->codigo);
            $this->assertSame($http, $e->http);
        }
    }
}
