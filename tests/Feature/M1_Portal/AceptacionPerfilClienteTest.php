<?php

namespace Tests\Feature\M1_Portal;

use App\Models\M1_Portal\Cliente;
use App\Models\Nucleo\Usuario;
use App\Services\M1_Portal\ClienteService;
use App\Services\M1_Portal\DTO\ClienteDTO;
use App\Services\M1_Portal\DTO\DireccionClienteDTO;
use Tests\Doubles\M1\IdentidadDoble;
use Tests\Feature\M1_Portal\Concerns\CreaTablasDeClientes;

/**
 * Tarea 16 del plan del PC4: ver y actualizar los datos del cliente por las
 * rutas web, con la regla de acceso RN-M1-16 (cada persona solo ve y edita sus
 * propios datos; cualquier otro caso responde 404 ERR-003).
 *
 * La sesión del núcleo se simula con actingAs sobre una cuenta en memoria.
 * Tablas en memoria y doble del núcleo: ver CreaTablasDeClientes.
 */
class AceptacionPerfilClienteTest extends PruebaM1
{
    use CreaTablasDeClientes;

    private int $ana;

    private int $luis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararClientes();

        $servicio = app(ClienteService::class);
        $this->ana = $servicio->registrar($this->cliente('Ana', 'ana@example.com', '5-0123-0456'), $this->direccion(), 'secreta123');
        $this->luis = $servicio->registrar($this->cliente('Luis', 'luis@example.com', '1-1111-1111'), $this->direccion(), 'secreta123');
    }

    // ---------------------------------------------------------------
    // GET /clientes/sesion
    // ---------------------------------------------------------------

    public function test_la_persona_con_sesion_obtiene_sus_datos_e_id_cliente(): void
    {
        $this->comoAna()->getJson('/api/m1/v1/clientes/sesion')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson([
                'id_cliente' => $this->ana,
                'nombre' => 'Ana',
                'apellidos' => 'Mora Rojas',
                'correo_electronico' => 'ana@example.com',
                'telefono' => '8888-0000',
                'direccion_envio' => '200 m norte del parque, Liberia, Liberia, Guanacaste',
                'identificacion_tributaria' => '5-0123-0456',
            ]);
    }

    public function test_una_cuenta_que_no_es_de_un_cliente_recibe_err_003(): void
    {
        $this->actingAs($this->cuenta(9999), 'web')
            ->getJson('/api/m1/v1/clientes/sesion')
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-003');
    }

    public function test_sin_sesion_responde_401_err_001(): void
    {
        $this->getJson('/api/m1/v1/clientes/sesion')->assertUnauthorized()->assertJsonPath('error.codigo', 'ERR-001');
        $this->getJson("/api/m1/v1/clientes/{$this->ana}")->assertUnauthorized()->assertJsonPath('error.codigo', 'ERR-001');
        $this->putJson("/api/m1/v1/clientes/{$this->ana}", $this->datos())->assertUnauthorized()->assertJsonPath('error.codigo', 'ERR-001');
    }

    // ---------------------------------------------------------------
    // GET /clientes/{id_cliente} (RN-M1-16)
    // ---------------------------------------------------------------

    public function test_la_persona_ve_sus_propios_datos(): void
    {
        $this->comoAna()->getJson("/api/m1/v1/clientes/{$this->ana}")
            ->assertOk()
            ->assertJsonPath('id_cliente', $this->ana)
            ->assertJsonPath('correo_electronico', 'ana@example.com')
            ->assertJsonPath('identificacion_tributaria', '5-0123-0456');
    }

    public function test_no_puede_ver_los_datos_de_otro_cliente(): void
    {
        $this->comoAna()->getJson("/api/m1/v1/clientes/{$this->luis}")
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-003')
            ->assertJsonMissing(['correo_electronico' => 'luis@example.com'])
            ->assertJsonMissing(['identificacion_tributaria' => '1-1111-1111']);
    }

    public function test_un_cliente_inexistente_responde_igual_que_uno_ajeno(): void
    {
        $ajeno = $this->comoAna()->getJson("/api/m1/v1/clientes/{$this->luis}");
        $inexistente = $this->comoAna()->getJson('/api/m1/v1/clientes/99999');

        $inexistente->assertNotFound()->assertJsonPath('error.codigo', 'ERR-003');
        $this->assertSame($ajeno->json(), $inexistente->json(), 'No se revela si el cliente existe.');
    }

    public function test_una_cuenta_de_comercio_no_puede_consultar_clientes(): void
    {
        $this->actingAs($this->cuenta(9999), 'web')
            ->getJson("/api/m1/v1/clientes/{$this->ana}")
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-003');
    }

    // ---------------------------------------------------------------
    // PUT /clientes/{id_cliente}
    // ---------------------------------------------------------------

    public function test_la_persona_actualiza_sus_datos_sin_cambiar_cedula_ni_direccion(): void
    {
        $this->comoAna()->putJson("/api/m1/v1/clientes/{$this->ana}", $this->datos([
            'nombre' => 'Ana María',
            'telefono' => '8777-0000',
            'cedula' => '9-9999-9999',
            'direccion_envio' => 'Otra dirección que se ignora',
        ]))->assertOk()->assertExactJson(['id_cliente' => $this->ana]);

        $cliente = Cliente::query()->findOrFail($this->ana);
        $this->assertSame('Ana María', $cliente->nombre);
        $this->assertSame('8777-0000', $cliente->telefono);
        $this->assertSame('5-0123-0456', $cliente->cedula);
        $this->assertSame('200 m norte del parque, Liberia, Liberia, Guanacaste', $cliente->aDTO()->direccionEnvio);
    }

    public function test_no_puede_editar_los_datos_de_otro_cliente(): void
    {
        $this->comoAna()->putJson("/api/m1/v1/clientes/{$this->luis}", $this->datos(['nombre' => 'Intruso', 'correo_electronico' => 'luis@example.com']))
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-003');

        $this->assertSame('Luis', Cliente::query()->findOrFail($this->luis)->nombre);
    }

    public function test_cambiar_el_correo_responde_422_mientras_el_nucleo_no_lo_permita(): void
    {
        $this->comoAna()->putJson("/api/m1/v1/clientes/{$this->ana}", $this->datos(['correo_electronico' => 'ana.nueva@example.com']))
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonPath('error.detalles.correo_electronico', ClienteService::CORREO_SIN_CAMBIO);
    }

    public function test_usar_el_correo_de_otro_cliente_responde_409_err_m1_001(): void
    {
        $this->comoAna()->putJson("/api/m1/v1/clientes/{$this->ana}", $this->datos(['correo_electronico' => 'LUIS@example.com']))
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M1-001');
    }

    public function test_datos_invalidos_responden_422_con_detalle_por_campo(): void
    {
        $this->comoAna()->putJson("/api/m1/v1/clientes/{$this->ana}", $this->datos(['apellidos' => str_repeat('b', 121), 'telefono' => '']))
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.apellidos', 'No puede tener más de 120 caracteres.')
            ->assertJsonPath('error.detalles.telefono', 'Es obligatorio.');
    }

    // ---------------------------------------------------------------
    // Apoyo
    // ---------------------------------------------------------------

    private function comoAna(): static
    {
        return $this->actingAs($this->cuenta(IdentidadDoble::PRIMER_ID), 'web');
    }

    private function cuenta(int $idUsuario): Usuario
    {
        $usuario = new Usuario;
        $usuario->forceFill(['id_usuario' => $idUsuario]);

        return $usuario;
    }

    /**
     * Cuerpo de PUT según DatosCliente 1.2.0, con los datos actuales de Ana.
     *
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'nombre' => 'Ana',
            'apellidos' => 'Mora Rojas',
            'correo_electronico' => 'ana@example.com',
            'telefono' => '8888-0000',
            ...$cambios,
        ];
    }

    private function cliente(string $nombre, string $correo, string $cedula): ClienteDTO
    {
        return new ClienteDTO(null, $nombre, 'Mora Rojas', $correo, '8888-0000', $cedula, '');
    }

    private function direccion(): DireccionClienteDTO
    {
        return new DireccionClienteDTO(null, null, 'Casa', 'Ana Mora', '8888-0000', 'Guanacaste', 'Liberia', 'Liberia', '200 m norte del parque', true);
    }
}
