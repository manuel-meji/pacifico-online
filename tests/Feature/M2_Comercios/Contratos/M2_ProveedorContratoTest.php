<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios\Contratos;

use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\ComisionSubpedido;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/**
 * Pruebas de contrato de M2 como proveedor (OpenAPI v1.2.1, /api/m2/v1):
 * forma de las respuestas, códigos ErrorEstandar y reglas de x-autorizacion.
 */
class M2_ProveedorContratoTest extends TestCase
{
    use PreparaM2;

    private const ERROR = ['error' => ['codigo', 'mensaje', 'detalles']];

    private Comercio $activo;

    private Comercio $otro;

    private Comercio $suspendido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activo = $this->crearComercio(['nombre_comercial' => 'Soluciones del Milenio']);
        $this->crearComision($this->activo, 'porcentaje', 8);
        $this->otro = $this->crearComercio(['nombre_comercial' => 'Temulandia']);
        $this->suspendido = $this->crearComercio(['estado' => Comercio::SUSPENDIDO]);
    }

    // ---- Operaciones públicas --------------------------------------------

    public function test_listar_comercios_habilitados_cumple_el_esquema(): void
    {
        $this->getJson('/api/m2/v1/comercios/publico?pagina=1&por_pagina=10')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonStructure(['pagina', 'por_pagina', 'total', 'comercios' => [['id_comercio', 'nombre_comercial', 'logo_url', 'estado', 'destacado']]]);
    }

    public function test_listar_con_parametro_invalido_responde_422_err_005(): void
    {
        $this->getJson('/api/m2/v1/comercios/publico?por_pagina=80')
            ->assertStatus(422)
            ->assertJsonStructure(self::ERROR)
            ->assertJsonPath('error.codigo', 'ERR-005');
    }

    public function test_resumen_por_lote_omite_no_habilitados_y_valida_el_maximo(): void
    {
        $this->postJson('/api/m2/v1/comercios/publico/lote', ['ids_comercio' => [$this->activo->id_comercio, $this->suspendido->id_comercio]])
            ->assertOk()
            ->assertJsonCount(1, 'comercios')
            ->assertJsonPath('comercios.0.id_comercio', $this->activo->id_comercio);

        $this->postJson('/api/m2/v1/comercios/publico/lote', ['ids_comercio' => range(1, 51)])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005');
    }

    public function test_micrositio_publico_responde_200_o_404_err_004(): void
    {
        $this->getJson("/api/m2/v1/comercios/{$this->activo->id_comercio}/publico")
            ->assertOk()
            ->assertJsonPath('estado', 'Activo')
            ->assertJsonStructure(['id_comercio', 'nombre_comercial', 'descripcion', 'logo_url', 'banners', 'galeria', 'horarios', 'contacto' => ['telefono', 'correo', 'direccion'], 'redes_sociales', 'politicas', 'estado']);

        $this->getJson("/api/m2/v1/comercios/{$this->suspendido->id_comercio}/publico")
            ->assertNotFound()
            ->assertJsonStructure(self::ERROR)
            ->assertJsonPath('error.codigo', 'ERR-004');
    }

    // ---- Operaciones con sesión ------------------------------------------

    public function test_sin_sesion_responde_401_err_001(): void
    {
        $this->getJson("/api/m2/v1/comercios/{$this->activo->id_comercio}/estado")
            ->assertUnauthorized()
            ->assertJsonPath('error.codigo', 'ERR-001');
    }

    public function test_cliente_no_puede_consultar_estado_ni_comision_403_err_002(): void
    {
        $cliente = $this->usuario('cliente');

        $this->actingAs($cliente, 'sanctum')->getJson("/api/m2/v1/comercios/{$this->activo->id_comercio}/estado")
            ->assertForbidden()->assertJsonPath('error.codigo', 'ERR-002');
        $this->actingAs($cliente, 'sanctum')->getJson('/api/m2/v1/subpedidos/7001/comision')
            ->assertForbidden()->assertJsonPath('error.codigo', 'ERR-002');
    }

    public function test_encargado_de_comercio_solo_consulta_el_suyo_y_otro_responde_404_err_003(): void
    {
        $encargado = $this->usuario('encargado_comercio', $this->activo->id_comercio);

        $this->actingAs($encargado, 'sanctum')->getJson("/api/m2/v1/comercios/{$this->activo->id_comercio}/estado")
            ->assertOk()
            ->assertExactJson(['id_comercio' => $this->activo->id_comercio, 'estado' => 'Activo', 'activo' => true, 'motivo_estado' => null]);

        $this->actingAs($encargado, 'sanctum')->getJson("/api/m2/v1/comercios/{$this->otro->id_comercio}/datos-fiscales")
            ->assertNotFound()->assertJsonPath('error.codigo', 'ERR-003');
    }

    public function test_encargado_de_plataforma_consulta_cualquier_comercio(): void
    {
        $plataforma = $this->usuario('encargado_plataforma');

        $this->actingAs($plataforma, 'sanctum')->getJson("/api/m2/v1/comercios/{$this->suspendido->id_comercio}/estado")
            ->assertOk()->assertJsonPath('activo', false);
        $this->actingAs($plataforma, 'sanctum')->getJson("/api/m2/v1/comercios/{$this->activo->id_comercio}/sedes")
            ->assertOk()->assertJsonStructure(['id_comercio', 'sedes']);
        $this->actingAs($plataforma, 'sanctum')->getJson("/api/m2/v1/comercios/{$this->activo->id_comercio}/datos-fiscales")
            ->assertOk()
            ->assertJsonPath('comision.tipo', 'porcentaje')
            ->assertJsonStructure(['id_comercio', 'cedula_juridica', 'razon_social', 'direccion_fiscal', 'inscrito_como_contribuyente', 'comision' => ['tipo', 'valor', 'base_calculo', 'moneda']]);
        $this->actingAs($plataforma, 'sanctum')->getJson('/api/m2/v1/comercios/999999/estado')
            ->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
    }

    public function test_cliente_consulta_politicas_de_envio_solo_de_comercios_habilitados(): void
    {
        $cliente = $this->usuario('cliente');

        $this->actingAs($cliente, 'sanctum')->getJson("/api/m2/v1/comercios/{$this->activo->id_comercio}/politicas-envio")
            ->assertOk()->assertJsonStructure(['id_comercio', 'politicas', 'tarifas']);
        $this->actingAs($cliente, 'sanctum')->getJson("/api/m2/v1/comercios/{$this->suspendido->id_comercio}/politicas-envio")
            ->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
    }

    public function test_comision_de_subpedido_para_m7(): void
    {
        ComisionSubpedido::query()->create([
            'id_subpedido' => 7001, 'id_comercio' => $this->activo->id_comercio, 'subtotal_base' => 24500,
            'monto' => 1960, 'moneda' => 'CRC', 'id_evento' => '6b1f5c0e-2f0a-4b8e-9d55-0d6a0c1c7a01',
        ]);
        $plataforma = $this->usuario('encargado_plataforma');
        $encargadoOtro = $this->usuario('encargado_comercio', $this->otro->id_comercio);

        $this->actingAs($plataforma, 'sanctum')->getJson('/api/m2/v1/subpedidos/7001/comision')
            ->assertOk()
            ->assertExactJson(['id_subpedido' => 7001, 'id_comercio' => $this->activo->id_comercio, 'monto_comision' => 1960, 'base_utilizada' => 24500, 'moneda' => 'CRC']);
        $this->actingAs($encargadoOtro, 'sanctum')->getJson('/api/m2/v1/subpedidos/7001/comision')
            ->assertNotFound()->assertJsonPath('error.codigo', 'ERR-003');
        $this->actingAs($plataforma, 'sanctum')->getJson('/api/m2/v1/subpedidos/7002/comision')
            ->assertNotFound()->assertJsonPath('error.codigo', 'ERR-004');
    }

    public function test_usuario_inactivo_responde_403_err_002(): void
    {
        $inactivo = $this->usuario('encargado_plataforma', null, false);

        $this->actingAs($inactivo, 'sanctum')->getJson("/api/m2/v1/comercios/{$this->activo->id_comercio}/estado")
            ->assertForbidden()->assertJsonPath('error.codigo', 'ERR-002');
    }
}
