<?php

namespace Tests\Feature\M1_Portal;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M1_Portal\Cliente;
use App\Models\M1_Portal\DireccionCliente;
use Tests\Doubles\M1\IdentidadDoble;
use Tests\Feature\M1_Portal\Concerns\CreaTablasDeClientes;

/**
 * Prueba de aceptación «registrarse como cliente» (tarea 25 del plan del PC4,
 * CU-010, RF-010): POST /api/m1/v1/clientes de punta a punta, desde la ruta
 * web hasta las tablas de M1, con el núcleo reemplazado por un doble.
 *
 * Tablas en memoria y doble del núcleo: ver CreaTablasDeClientes.
 */
class AceptacionRegistrarClienteTest extends PruebaM1
{
    use CreaTablasDeClientes;

    private const RUTA = '/api/m1/v1/clientes';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararClientes();
    }

    public function test_una_persona_se_registra_y_queda_como_cliente_con_su_direccion_principal(): void
    {
        $respuesta = $this->postJson(self::RUTA, $this->registro());

        $respuesta->assertCreated()->assertExactJson(['id_cliente' => 1]);

        $cliente = Cliente::query()->findOrFail(1);
        $this->assertSame(IdentidadDoble::PRIMER_ID, $cliente->id_usuario);
        $this->assertSame('Ana', $cliente->nombre);
        $this->assertSame('5-0123-0456', $cliente->cedula);

        $direccion = DireccionCliente::query()->where('id_cliente', 1)->sole();
        $this->assertTrue($direccion->es_principal);
        $this->assertSame('Liberia', $direccion->canton);

        $this->assertArrayNotHasKey('contrasena', $cliente->getAttributes(), 'M1 no guarda la contraseña.');
        $this->assertSame('secreta123', $this->identidad->datos['contrasena'], 'La contraseña se entrega al núcleo.');
    }

    public function test_el_correo_repetido_responde_409_err_m1_001(): void
    {
        $this->postJson(self::RUTA, $this->registro())->assertCreated();

        $this->postJson(self::RUTA, $this->registro(['correo_electronico' => 'ANA@example.com', 'cedula' => '1-1111-1111']))
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M1-001');

        $this->assertSame(1, Cliente::query()->count());
    }

    public function test_los_datos_invalidos_responden_422_err_005_con_detalle_por_campo(): void
    {
        $datos = $this->registro(['nombre' => str_repeat('a', 81), 'cedula' => '']);
        $datos['direccion']['provincia'] = '';

        $this->postJson(self::RUTA, $datos)
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005')
            ->assertJsonPath('error.detalles.nombre', 'No puede tener más de 80 caracteres.')
            ->assertJsonPath('error.detalles.cedula', 'Es obligatorio.')
            ->assertJsonPath('error.detalles', fn (array $detalles): bool => isset($detalles['direccion.provincia']));

        $this->assertSame(0, $this->identidad->llamadas, 'Con datos inválidos no se pide la cuenta al núcleo.');
        $this->assertSame(0, Cliente::query()->count());
    }

    public function test_sin_direccion_responde_422(): void
    {
        $datos = $this->registro();
        unset($datos['direccion']);

        $this->postJson(self::RUTA, $datos)
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.direccion', 'Es obligatorio.');
    }

    public function test_si_el_nucleo_rechaza_la_cuenta_no_queda_ningun_cliente(): void
    {
        $this->identidad->error = ErrorNucleoException::validacionFallida(['correo' => ['El correo ya está registrado.']]);

        $this->postJson(self::RUTA, $this->registro())
            ->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M1-001');

        $this->assertSame(0, Cliente::query()->count());
        $this->assertSame(0, DireccionCliente::query()->count());
    }

    public function test_la_respuesta_nunca_devuelve_la_contrasena(): void
    {
        $respuesta = $this->postJson(self::RUTA, $this->registro());

        $this->assertStringNotContainsString('secreta123', $respuesta->getContent());
    }

    public function test_la_ruta_limita_a_10_intentos_por_minuto(): void
    {
        for ($intento = 1; $intento <= 10; $intento++) {
            $this->postJson(self::RUTA, [])->assertStatus(422);
        }

        $this->postJson(self::RUTA, [])->assertStatus(429);
    }

    /**
     * Cuerpo válido según RegistroCliente 1.2.0.
     *
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function registro(array $cambios = []): array
    {
        return [
            'nombre' => 'Ana',
            'apellidos' => 'Mora Rojas',
            'correo_electronico' => 'ana@example.com',
            'telefono' => '8888-0000',
            'cedula' => '5-0123-0456',
            'contrasena' => 'secreta123',
            'direccion' => [
                'alias_direccion' => 'Casa',
                'nombre_destinatario' => 'Ana Mora',
                'telefono_destinatario' => '8888-0000',
                'provincia' => 'Guanacaste',
                'canton' => 'Liberia',
                'distrito' => 'Liberia',
                'otras_senas' => '200 m norte del parque',
            ],
            ...$cambios,
        ];
    }
}
