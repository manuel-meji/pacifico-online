<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\ClienteNoEncontradoException;
use App\Contracts\M1_Portal\Exceptions\CorreoYaRegistradoException;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\DireccionNoEncontradaException;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Enums\Nucleo\RolUsuario;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M1_Portal\Cliente;
use App\Models\M1_Portal\DireccionCliente;
use App\Services\M1_Portal\ClienteService;
use App\Services\M1_Portal\DTO\ClienteDTO;
use App\Services\M1_Portal\DTO\DireccionClienteDTO;
use Tests\Doubles\M1\IdentidadDoble;
use Tests\Feature\M1_Portal\Concerns\CreaTablasDeClientes;

/**
 * ClienteService (tareas 15 a 18 del plan del PC4).
 *
 * Tablas en memoria y doble del núcleo: ver CreaTablasDeClientes.
 */
class ClienteServiceTest extends PruebaM1
{
    use CreaTablasDeClientes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararClientes();
    }

    // ---------------------------------------------------------------
    // Registro (tarea 15, CU-010)
    // ---------------------------------------------------------------

    public function test_registrar_crea_la_cuenta_el_cliente_y_su_direccion_principal(): void
    {
        $idCliente = $this->servicio()->registrar($this->cliente(), $this->direccion(esPrincipal: false), 'secreta123');

        $cliente = Cliente::query()->findOrFail($idCliente);
        $this->assertSame(IdentidadDoble::PRIMER_ID, $cliente->id_usuario);
        $this->assertSame('5-0123-0456', $cliente->cedula);
        $this->assertSame(Cliente::ESTADO_ACTIVO, $cliente->estado_cliente);

        $direcciones = DireccionCliente::query()->where('id_cliente', $idCliente)->get();
        $this->assertCount(1, $direcciones);
        $this->assertTrue($direcciones[0]->es_principal, 'La dirección del registro siempre queda como principal.');

        $this->assertSame(RolUsuario::Cliente, $this->identidad->rol);
        $this->assertSame([
            'nombre' => 'Ana Mora Rojas',
            'correo' => 'ana@example.com',
            'contrasena' => 'secreta123',
        ], $this->identidad->datos);
    }

    public function test_tras_registrar_obtener_cliente_arma_direccion_envio_con_la_principal(): void
    {
        $idCliente = $this->servicio()->registrar($this->cliente(), $this->direccion(), 'secreta123');

        $cliente = $this->servicio()->obtener($idCliente);

        $this->assertSame($idCliente, $cliente->idCliente);
        $this->assertSame('8888-0000', $cliente->telefono);
        $this->assertSame('200 m norte del parque, Liberia, Liberia, Guanacaste', $cliente->direccionEnvio);
    }

    public function test_correo_repetido_sin_distinguir_mayusculas_no_llega_al_nucleo(): void
    {
        $this->servicio()->registrar($this->cliente(), $this->direccion(), 'secreta123');

        try {
            $this->servicio()->registrar(
                $this->cliente(correo: 'ANA@Example.com', cedula: '1-1111-1111'),
                $this->direccion(),
                'secreta123',
            );
            $this->fail('Debió lanzar CorreoYaRegistradoException (RN-M1-14).');
        } catch (CorreoYaRegistradoException $excepcion) {
            $this->assertSame('ERR-M1-001', $excepcion->codigo());
        }

        $this->assertSame(1, $this->identidad->llamadas);
        $this->assertSame(1, Cliente::query()->count());
    }

    public function test_cedula_repetida_se_rechaza_con_detalle_en_cedula(): void
    {
        $this->servicio()->registrar($this->cliente(), $this->direccion(), 'secreta123');

        try {
            $this->servicio()->registrar($this->cliente(correo: 'otra@example.com'), $this->direccion(), 'secreta123');
            $this->fail('Debió lanzar DatosInvalidosException.');
        } catch (DatosInvalidosException $excepcion) {
            $this->assertSame(['cedula' => 'Ya hay un cliente registrado con esa cédula.'], $excepcion->detalles());
        }
    }

    public function test_si_el_nucleo_rechaza_el_correo_responde_err_m1_001_y_no_queda_nada(): void
    {
        $this->identidad->error = ErrorNucleoException::validacionFallida(['correo' => ['El correo ya está registrado.']]);

        try {
            $this->servicio()->registrar($this->cliente(), $this->direccion(), 'secreta123');
            $this->fail('Debió lanzar CorreoYaRegistradoException.');
        } catch (CorreoYaRegistradoException) {
        }

        $this->assertSame(0, Cliente::query()->count());
        $this->assertSame(0, DireccionCliente::query()->count());
    }

    public function test_otros_errores_del_nucleo_llegan_como_err_005_por_campo(): void
    {
        $this->identidad->error = ErrorNucleoException::validacionFallida(['contrasena' => ['Debe tener al menos 8 caracteres.']]);

        try {
            $this->servicio()->registrar($this->cliente(), $this->direccion(), 'corta');
            $this->fail('Debió lanzar DatosInvalidosException.');
        } catch (DatosInvalidosException $excepcion) {
            $this->assertSame(['contrasena' => 'Debe tener al menos 8 caracteres.'], $excepcion->detalles());
        }

        $this->assertSame(0, Cliente::query()->count());
    }

    public function test_el_nombre_de_la_cuenta_se_recorta_al_largo_de_usuarios(): void
    {
        $this->servicio()->registrar(
            $this->cliente(nombre: str_repeat('a', 80), apellidos: str_repeat('b', 120)),
            $this->direccion(),
            'secreta123',
        );

        $this->assertSame(150, mb_strlen($this->identidad->datos['nombre']));
        $this->assertSame(80, mb_strlen(Cliente::query()->value('nombre')), 'En clientes queda el nombre completo.');
    }

    // ---------------------------------------------------------------
    // Consultas para M5, M6 y núcleo (tareas 16 a 18)
    // ---------------------------------------------------------------

    public function test_obtener_un_cliente_inexistente_lanza_err_004(): void
    {
        $this->expectException(ClienteNoEncontradoException::class);

        $this->servicio()->obtener(999);
    }

    public function test_obtener_id_por_usuario_y_err_004_si_la_cuenta_no_es_de_un_cliente(): void
    {
        $idCliente = $this->servicio()->registrar($this->cliente(), $this->direccion(), 'secreta123');

        $this->assertSame($idCliente, $this->servicio()->obtenerIdPorUsuario(IdentidadDoble::PRIMER_ID));

        $this->expectException(ClienteNoEncontradoException::class);
        $this->servicio()->obtenerIdPorUsuario(4242);
    }

    public function test_listar_direcciones_pone_la_principal_primero(): void
    {
        $idCliente = $this->servicio()->registrar($this->cliente(), $this->direccion(alias: 'Casa'), 'secreta123');
        $this->servicio()->agregarDireccion($idCliente, $this->direccion(alias: 'Trabajo', esPrincipal: false));
        $this->servicio()->agregarDireccion($idCliente, $this->direccion(alias: 'Finca', esPrincipal: true));

        $alias = array_map(fn (DireccionClienteDTO $direccion): string => $direccion->aliasDireccion, $this->servicio()->listarDirecciones($idCliente));

        $this->assertSame(['Finca', 'Casa', 'Trabajo'], $alias);
    }

    public function test_listar_direcciones_de_un_cliente_inexistente_lanza_err_004(): void
    {
        $this->expectException(ClienteNoEncontradoException::class);

        $this->servicio()->listarDirecciones(999);
    }

    public function test_no_se_puede_obtener_la_direccion_de_otro_cliente(): void
    {
        $ana = $this->servicio()->registrar($this->cliente(), $this->direccion(), 'secreta123');
        $luis = $this->servicio()->registrar($this->cliente(correo: 'luis@example.com', cedula: '1-1111-1111'), $this->direccion(), 'secreta123');
        $direccionDeLuis = $this->servicio()->listarDirecciones($luis)[0]->idDireccionCliente;

        $this->expectException(DireccionNoEncontradaException::class);

        $this->servicio()->obtenerDireccion($ana, $direccionDeLuis);
    }

    // ---------------------------------------------------------------
    // Actualización del cliente
    // ---------------------------------------------------------------

    public function test_actualizar_cambia_los_datos_y_conserva_cedula_y_direcciones(): void
    {
        $idCliente = $this->servicio()->registrar($this->cliente(), $this->direccion(), 'secreta123');

        $this->servicio()->actualizar($idCliente, $this->cliente(nombre: 'Ana María', cedula: '9-9999-9999', telefono: '8777-0000'));

        $cliente = $this->servicio()->obtener($idCliente);
        $this->assertSame('Ana María', $cliente->nombre);
        $this->assertSame('8777-0000', $cliente->telefono);
        $this->assertSame('5-0123-0456', $cliente->cedula);
        $this->assertSame('200 m norte del parque, Liberia, Liberia, Guanacaste', $cliente->direccionEnvio);
    }

    public function test_cambiar_el_correo_se_rechaza_mientras_el_nucleo_no_ofrezca_actualizar_correo(): void
    {
        $idCliente = $this->servicio()->registrar($this->cliente(), $this->direccion(), 'secreta123');

        try {
            $this->servicio()->actualizar($idCliente, $this->cliente(nombre: 'Ana María', correo: 'ana.nueva@example.com'));
            $this->fail('Debió lanzar DatosInvalidosException.');
        } catch (DatosInvalidosException $excepcion) {
            $this->assertSame(['correo_electronico' => ClienteService::CORREO_SIN_CAMBIO], $excepcion->detalles());
        }

        $cliente = $this->servicio()->obtener($idCliente);
        $this->assertSame('ana@example.com', $cliente->correoElectronico);
        $this->assertSame('Ana', $cliente->nombre, 'Si se rechaza el correo, no cambia nada.');
    }

    public function test_actualizar_con_el_correo_de_otro_cliente_lanza_err_m1_001(): void
    {
        $this->servicio()->registrar($this->cliente(), $this->direccion(), 'secreta123');
        $luis = $this->servicio()->registrar($this->cliente(correo: 'luis@example.com', cedula: '1-1111-1111'), $this->direccion(), 'secreta123');

        $this->expectException(CorreoYaRegistradoException::class);

        $this->servicio()->actualizar($luis, $this->cliente(correo: 'Ana@example.com'));
    }

    public function test_actualizar_conservando_el_propio_correo_no_es_duplicado(): void
    {
        $idCliente = $this->servicio()->registrar($this->cliente(), $this->direccion(), 'secreta123');

        $this->assertSame($idCliente, $this->servicio()->actualizar($idCliente, $this->cliente(correo: 'ANA@example.com')));
    }

    // ---------------------------------------------------------------
    // Direcciones (tarea 17)
    // ---------------------------------------------------------------

    public function test_una_direccion_nueva_principal_desmarca_la_anterior(): void
    {
        $idCliente = $this->servicio()->registrar($this->cliente(), $this->direccion(alias: 'Casa'), 'secreta123');

        $nueva = $this->servicio()->agregarDireccion($idCliente, $this->direccion(alias: 'Trabajo', esPrincipal: true));

        $this->assertTrue($nueva->esPrincipal);
        $this->assertSame(1, DireccionCliente::query()->where('id_cliente', $idCliente)->where('es_principal', true)->count());
    }

    public function test_alias_repetido_para_el_mismo_cliente_se_rechaza(): void
    {
        $idCliente = $this->servicio()->registrar($this->cliente(), $this->direccion(alias: 'Casa'), 'secreta123');

        try {
            $this->servicio()->agregarDireccion($idCliente, $this->direccion(alias: 'Casa', esPrincipal: false));
            $this->fail('Debió lanzar DatosInvalidosException.');
        } catch (DatosInvalidosException $excepcion) {
            $this->assertSame(['alias_direccion' => 'Ya tiene una dirección con ese alias.'], $excepcion->detalles());
        }
    }

    public function test_agregar_direccion_a_un_cliente_inexistente_lanza_err_004(): void
    {
        $this->expectException(ClienteNoEncontradoException::class);

        $this->servicio()->agregarDireccion(999, $this->direccion());
    }

    public function test_la_principal_no_se_desmarca_directamente(): void
    {
        $idCliente = $this->servicio()->registrar($this->cliente(), $this->direccion(alias: 'Casa'), 'secreta123');
        $principal = $this->servicio()->listarDirecciones($idCliente)[0];

        try {
            $this->servicio()->actualizarDireccion($idCliente, $principal->idDireccionCliente, $this->direccion(alias: 'Casa', esPrincipal: false));
            $this->fail('Debió lanzar DatosInvalidosException.');
        } catch (DatosInvalidosException $excepcion) {
            $this->assertArrayHasKey('es_principal', $excepcion->detalles());
        }

        $this->assertTrue(DireccionCliente::query()->findOrFail($principal->idDireccionCliente)->es_principal);
    }

    public function test_marcar_otra_direccion_como_principal_cambia_la_direccion_envio(): void
    {
        $idCliente = $this->servicio()->registrar($this->cliente(), $this->direccion(alias: 'Casa'), 'secreta123');
        $trabajo = $this->servicio()->agregarDireccion($idCliente, $this->direccion(alias: 'Trabajo', otrasSenas: 'Del hospital 100 m sur', esPrincipal: false));

        $editada = $this->servicio()->actualizarDireccion(
            $idCliente,
            $trabajo->idDireccionCliente,
            $this->direccion(alias: 'Trabajo', otrasSenas: 'Del hospital 100 m sur', esPrincipal: true),
        );

        $this->assertTrue($editada->esPrincipal);
        $this->assertSame('Del hospital 100 m sur, Liberia, Liberia, Guanacaste', $this->servicio()->obtener($idCliente)->direccionEnvio);
        $this->assertSame(1, DireccionCliente::query()->where('id_cliente', $idCliente)->where('es_principal', true)->count());
    }

    public function test_actualizar_la_direccion_de_otro_cliente_lanza_err_004(): void
    {
        $ana = $this->servicio()->registrar($this->cliente(), $this->direccion(), 'secreta123');
        $luis = $this->servicio()->registrar($this->cliente(correo: 'luis@example.com', cedula: '1-1111-1111'), $this->direccion(), 'secreta123');
        $direccionDeLuis = $this->servicio()->listarDirecciones($luis)[0]->idDireccionCliente;

        $this->expectException(DireccionNoEncontradaException::class);

        $this->servicio()->actualizarDireccion($ana, $direccionDeLuis, $this->direccion());
    }

    // ---------------------------------------------------------------
    // De punta a punta por la fachada (lo que reciben M5 y M6)
    // ---------------------------------------------------------------

    public function test_por_la_fachada_registrar_y_obtener_cumplen_el_contrato(): void
    {
        $portal = app(PortalPublicoContract::class);

        $idCliente = $portal->registrarCliente([
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
        ]);

        $this->assertSame([
            'id_cliente' => $idCliente,
            'nombre' => 'Ana',
            'apellidos' => 'Mora Rojas',
            'correo_electronico' => 'ana@example.com',
            'telefono' => '8888-0000',
            'direccion_envio' => '200 m norte del parque, Liberia, Liberia, Guanacaste',
            'identificacion_tributaria' => '5-0123-0456',
        ], $portal->obtenerCliente($idCliente));

        $direcciones = $portal->listarDireccionesCliente($idCliente);
        $this->assertCount(1, $direcciones);
        $this->assertSame($direcciones[0], $portal->obtenerDireccionCliente($idCliente, $direcciones[0]['id_direccion_cliente']));
        $this->assertSame($idCliente, $portal->obtenerIdClientePorUsuario(IdentidadDoble::PRIMER_ID));
    }

    // ---------------------------------------------------------------
    // Apoyo
    // ---------------------------------------------------------------

    private function servicio(): ClienteService
    {
        return app(ClienteService::class);
    }

    private function cliente(
        string $nombre = 'Ana',
        string $apellidos = 'Mora Rojas',
        string $correo = 'ana@example.com',
        string $telefono = '8888-0000',
        string $cedula = '5-0123-0456',
    ): ClienteDTO {
        return new ClienteDTO(null, $nombre, $apellidos, $correo, $telefono, $cedula, '');
    }

    private function direccion(
        string $alias = 'Casa',
        string $otrasSenas = '200 m norte del parque',
        bool $esPrincipal = true,
    ): DireccionClienteDTO {
        return new DireccionClienteDTO(null, null, $alias, 'Ana Mora', '8888-0000', 'Guanacaste', 'Liberia', 'Liberia', $otrasSenas, $esPrincipal);
    }
}
