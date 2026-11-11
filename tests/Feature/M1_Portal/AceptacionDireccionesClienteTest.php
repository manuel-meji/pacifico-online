<?php

namespace Tests\Feature\M1_Portal;

use App\Models\M1_Portal\DireccionCliente;
use App\Models\Nucleo\Usuario;
use App\Services\M1_Portal\ClienteService;
use App\Services\M1_Portal\DTO\ClienteDTO;
use App\Services\M1_Portal\DTO\DireccionClienteDTO;
use Tests\Doubles\M1\IdentidadDoble;
use Tests\Feature\M1_Portal\Concerns\CreaTablasDeClientes;

/**
 * Tarea 17 del plan del PC4: direcciones de envío por las rutas web, con la
 * regla de acceso RN-M1-16 (solo la propia persona ve y edita sus direcciones).
 *
 * - Cliente ajeno o inexistente en la ruta → 404 ERR-003.
 * - Dirección que no existe o es de otro cliente → 404 ERR-004.
 *
 * La sesión del núcleo se simula con actingAs sobre una cuenta en memoria.
 * Tablas en memoria y doble del núcleo: ver CreaTablasDeClientes.
 */
class AceptacionDireccionesClienteTest extends PruebaM1
{
    use CreaTablasDeClientes;

    private int $ana;

    private int $luis;

    private int $casaDeAna;

    private int $casaDeLuis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararClientes();

        $servicio = app(ClienteService::class);
        $this->ana = $servicio->registrar($this->cliente('ana@example.com', '5-0123-0456'), $this->direccionDTO(), 'secreta123');
        $this->luis = $servicio->registrar($this->cliente('luis@example.com', '1-1111-1111'), $this->direccionDTO(), 'secreta123');
        $this->casaDeAna = $servicio->listarDirecciones($this->ana)[0]->idDireccionCliente;
        $this->casaDeLuis = $servicio->listarDirecciones($this->luis)[0]->idDireccionCliente;
    }

    // ---------------------------------------------------------------
    // GET /clientes/{id}/direcciones
    // ---------------------------------------------------------------

    public function test_la_persona_lista_sus_direcciones_con_la_principal_primero(): void
    {
        $this->comoAna()->postJson($this->ruta(), $this->direccion(['alias_direccion' => 'Trabajo']))->assertCreated();

        $this->comoAna()->getJson($this->ruta())
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(2)
            ->assertJsonPath('0.alias_direccion', 'Casa')
            ->assertJsonPath('0.es_principal', true)
            ->assertJsonPath('1.alias_direccion', 'Trabajo')
            ->assertJsonPath('1.es_principal', false);
    }

    public function test_no_puede_listar_las_direcciones_de_otro_cliente(): void
    {
        $this->comoAna()->getJson($this->ruta($this->luis))
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-003');
    }

    public function test_sin_sesion_las_tres_rutas_responden_401(): void
    {
        $this->getJson($this->ruta())->assertUnauthorized()->assertJsonPath('error.codigo', 'ERR-001');
        $this->postJson($this->ruta(), $this->direccion())->assertUnauthorized();
        $this->putJson($this->ruta()."/{$this->casaDeAna}", $this->direccion())->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // POST /clientes/{id}/direcciones
    // ---------------------------------------------------------------

    public function test_agregar_devuelve_201_con_la_direccion_creada(): void
    {
        $respuesta = $this->comoAna()->postJson($this->ruta(), $this->direccion(['alias_direccion' => 'Trabajo']));

        $respuesta->assertCreated()
            ->assertJsonPath('id_cliente', $this->ana)
            ->assertJsonPath('alias_direccion', 'Trabajo')
            ->assertJsonPath('es_principal', false);
        $this->assertIsInt($respuesta->json('id_direccion_cliente'));
    }

    public function test_agregar_una_principal_desmarca_la_anterior(): void
    {
        $this->comoAna()->postJson($this->ruta(), $this->direccion(['alias_direccion' => 'Finca', 'es_principal' => true]))
            ->assertCreated()
            ->assertJsonPath('es_principal', true);

        $this->assertFalse(DireccionCliente::query()->findOrFail($this->casaDeAna)->es_principal);
        $this->assertSame(1, DireccionCliente::query()->where('id_cliente', $this->ana)->where('es_principal', true)->count());
    }

    public function test_agregar_con_alias_repetido_responde_422(): void
    {
        $this->comoAna()->postJson($this->ruta(), $this->direccion(['alias_direccion' => 'Casa']))
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.alias_direccion', 'Ya tiene una dirección con ese alias.');
    }

    public function test_agregar_con_datos_invalidos_responde_422_por_campo(): void
    {
        $this->comoAna()->postJson($this->ruta(), $this->direccion([
            'alias_direccion' => 'Trabajo',
            'provincia' => '',
            'telefono_destinatario' => 'llamar 8888',
            'es_principal' => 'si',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonPath('error.detalles.provincia', 'Es obligatorio.')
            ->assertJsonPath('error.detalles.telefono_destinatario', 'Solo admite dígitos, espacios, + y -.')
            ->assertJsonPath('error.detalles.es_principal', 'Debe ser verdadero o falso.');
    }

    public function test_no_puede_agregar_direcciones_a_otro_cliente(): void
    {
        $this->comoAna()->postJson($this->ruta($this->luis), $this->direccion(['alias_direccion' => 'Intrusa']))
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-003');

        $this->assertSame(1, DireccionCliente::query()->where('id_cliente', $this->luis)->count());
    }

    // ---------------------------------------------------------------
    // PUT /clientes/{id}/direcciones/{id_direccion}
    // ---------------------------------------------------------------

    public function test_actualizar_una_direccion_propia(): void
    {
        $this->comoAna()->putJson($this->ruta()."/{$this->casaDeAna}", $this->direccion([
            'otras_senas' => 'Casa esquinera, portón negro',
            'es_principal' => true,
        ]))
            ->assertOk()
            ->assertJsonPath('id_direccion_cliente', $this->casaDeAna)
            ->assertJsonPath('otras_senas', 'Casa esquinera, portón negro');

        $this->comoAna()->getJson('/api/m1/v1/clientes/sesion')
            ->assertJsonPath('direccion_envio', 'Casa esquinera, portón negro, Liberia, Liberia, Guanacaste');
    }

    public function test_no_puede_actualizar_la_direccion_de_otro_cliente_desde_su_ruta(): void
    {
        $this->comoAna()->putJson($this->ruta()."/{$this->casaDeLuis}", $this->direccion(['es_principal' => true]))
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-004');

        $this->assertSame('200 m norte del parque', DireccionCliente::query()->findOrFail($this->casaDeLuis)->otras_senas);
    }

    public function test_no_puede_usar_la_ruta_de_otro_cliente(): void
    {
        $this->comoAna()->putJson($this->ruta($this->luis)."/{$this->casaDeLuis}", $this->direccion(['es_principal' => true]))
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-003');
    }

    public function test_la_principal_no_se_desmarca_directamente(): void
    {
        $this->comoAna()->putJson($this->ruta()."/{$this->casaDeAna}", $this->direccion(['es_principal' => false]))
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonStructure(['error' => ['detalles' => ['es_principal']]]);

        $this->assertTrue(DireccionCliente::query()->findOrFail($this->casaDeAna)->es_principal);
    }

    public function test_marcar_otra_como_principal_cambia_la_direccion_de_envio(): void
    {
        $trabajo = $this->comoAna()->postJson($this->ruta(), $this->direccion([
            'alias_direccion' => 'Trabajo',
            'otras_senas' => 'Del hospital 100 m sur',
        ]))->json('id_direccion_cliente');

        $this->comoAna()->putJson($this->ruta()."/{$trabajo}", $this->direccion([
            'alias_direccion' => 'Trabajo',
            'otras_senas' => 'Del hospital 100 m sur',
            'es_principal' => true,
        ]))->assertOk()->assertJsonPath('es_principal', true);

        $this->comoAna()->getJson($this->ruta())
            ->assertJsonPath('0.alias_direccion', 'Trabajo')
            ->assertJsonPath('1.es_principal', false);
        $this->comoAna()->getJson('/api/m1/v1/clientes/sesion')
            ->assertJsonPath('direccion_envio', 'Del hospital 100 m sur, Liberia, Liberia, Guanacaste');
    }

    // ---------------------------------------------------------------
    // Apoyo
    // ---------------------------------------------------------------

    private function ruta(?int $idCliente = null): string
    {
        return '/api/m1/v1/clientes/'.($idCliente ?? $this->ana).'/direcciones';
    }

    private function comoAna(): static
    {
        $usuario = new Usuario;
        $usuario->forceFill(['id_usuario' => IdentidadDoble::PRIMER_ID]);

        return $this->actingAs($usuario, 'web');
    }

    /**
     * Cuerpo según DatosDireccionCliente.
     *
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function direccion(array $cambios = []): array
    {
        return [
            'alias_direccion' => 'Casa',
            'nombre_destinatario' => 'Ana Mora',
            'telefono_destinatario' => '8888-0000',
            'provincia' => 'Guanacaste',
            'canton' => 'Liberia',
            'distrito' => 'Liberia',
            'otras_senas' => '200 m norte del parque',
            ...$cambios,
        ];
    }

    private function cliente(string $correo, string $cedula): ClienteDTO
    {
        return new ClienteDTO(null, 'Ana', 'Mora Rojas', $correo, '8888-0000', $cedula, '');
    }

    private function direccionDTO(): DireccionClienteDTO
    {
        return new DireccionClienteDTO(null, null, 'Casa', 'Ana Mora', '8888-0000', 'Guanacaste', 'Liberia', 'Liberia', '200 m norte del parque', true);
    }
}
