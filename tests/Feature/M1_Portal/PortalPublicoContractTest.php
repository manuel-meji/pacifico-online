<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\ClienteNoEncontradoException;
use App\Contracts\M1_Portal\Exceptions\CorreoYaRegistradoException;
use App\Contracts\M1_Portal\Exceptions\CriterioInvalidoException;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Contracts\M1_Portal\Exceptions\DireccionNoEncontradaException;
use App\Contracts\M1_Portal\Exceptions\PortalPublicoException;
use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Contracts\Nucleo\ErrorEstandarInterface;
use App\Services\Nucleo\ManejadorErrores;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Tests\Doubles\M1\PortalPublicoFake;
use Tests\TestCase;

/**
 * La interfaz PHP de M1 coincide con lo publicado en el contrato v1.1.0
 * (contracts/m1-portal/v1/openapi.yaml, x-interfaz-php), las excepciones
 * llevan los códigos del contrato y el doble que usan M5, M6 y el núcleo se
 * comporta como el contrato describe.
 */
class PortalPublicoContractTest extends TestCase
{
    public function test_la_interfaz_tiene_exactamente_los_metodos_publicados_en_el_contrato(): void
    {
        $publicadas = $this->firmasPublicadas();
        $enCodigo = [];

        foreach ((new ReflectionClass(PortalPublicoContract::class))->getMethods() as $metodo) {
            $enCodigo[$metodo->getName()] = $this->firma($metodo);
        }

        ksort($publicadas);
        ksort($enCodigo);

        $this->assertSame($publicadas, $enCodigo);
    }

    public function test_el_contrato_ubica_la_interfaz_donde_esta_en_el_codigo(): void
    {
        $contrato = Yaml::parseFile(base_path('contracts/m1-portal/v1/openapi.yaml'))['x-interfaz-php'];

        $this->assertSame(PortalPublicoContract::class, $contrato['interfaz']);
        $this->assertFileExists(base_path($contrato['ubicacion']));
        $this->assertDirectoryExists(base_path($contrato['excepciones']['ubicacion']));
        $this->assertFileExists(base_path($contrato['doble_de_prueba']));
    }

    /**
     * @return array<string, array{class-string<PortalPublicoException>, string, int}>
     */
    public static function excepciones(): array
    {
        return [
            'cliente no encontrado' => [ClienteNoEncontradoException::class, 'ERR-004', 404],
            'dirección no encontrada' => [DireccionNoEncontradaException::class, 'ERR-004', 404],
            'recurso no encontrado' => [RecursoNoEncontradoException::class, 'ERR-004', 404],
            'criterio inválido' => [CriterioInvalidoException::class, 'ERR-005', 422],
            'datos inválidos' => [DatosInvalidosException::class, 'ERR-005', 422],
            'dependencia no disponible' => [DependenciaNoDisponibleException::class, 'ERR-006', 503],
            'correo ya registrado' => [CorreoYaRegistradoException::class, 'ERR-M1-001', 409],
        ];
    }

    /**
     * @param  class-string<PortalPublicoException>  $clase
     */
    #[DataProvider('excepciones')]
    public function test_cada_excepcion_expone_el_codigo_del_contrato_y_extiende_runtime_exception(string $clase, string $codigo, int $codigoHttp): void
    {
        $excepcion = new $clase;

        $this->assertInstanceOf(PortalPublicoException::class, $excepcion);
        $this->assertInstanceOf(RuntimeException::class, $excepcion);
        $this->assertInstanceOf(ErrorEstandarInterface::class, $excepcion);
        $this->assertSame($codigo, $excepcion->codigo());
        $this->assertSame($codigo, $excepcion->getCodigo());
        $this->assertSame($codigoHttp, $excepcion->getCodigoHttp());
        $this->assertSame('M1_Portal', $excepcion->getModulo());
        $this->assertNotSame('', $excepcion->getMessage());
    }

    public function test_el_manejador_del_nucleo_responde_las_excepciones_de_m1_con_el_formato_estandar(): void
    {
        $respuesta = ManejadorErrores::renderizar(
            new DatosInvalidosException(['correo_electronico' => 'No tiene un formato de correo válido.']),
            Request::create('/api/m1/v1/clientes', 'POST'),
        );

        $this->assertSame(422, $respuesta->getStatusCode());
        $this->assertSame('ERR-005', $respuesta->getData(true)['error']['codigo']);
        $this->assertSame(['correo_electronico' => 'No tiene un formato de correo válido.'], $respuesta->getData(true)['error']['detalles']);
    }

    public function test_datos_invalidos_lleva_el_detalle_por_campo(): void
    {
        $excepcion = new DatosInvalidosException(['correo_electronico' => 'No tiene un formato de correo válido.']);

        $this->assertSame(['correo_electronico' => 'No tiene un formato de correo válido.'], $excepcion->detalles());
    }

    public function test_el_doble_implementa_el_contrato(): void
    {
        $this->assertInstanceOf(PortalPublicoContract::class, new PortalPublicoFake);
    }

    public function test_m6_obtiene_el_cliente_con_la_estructura_del_esquema_cliente(): void
    {
        $cliente = (new PortalPublicoFake)->obtenerCliente(PortalPublicoFake::CLIENTE_CON_DIRECCIONES);

        $this->assertSame(
            ['id_cliente', 'nombre', 'apellidos', 'correo_electronico', 'telefono', 'direccion_envio'],
            array_keys($cliente),
        );
        $this->assertSame('200 m norte del parque, Liberia, Liberia, Guanacaste', $cliente['direccion_envio']);
    }

    public function test_un_cliente_inexistente_lanza_err_004(): void
    {
        $this->expectException(ClienteNoEncontradoException::class);

        (new PortalPublicoFake)->obtenerCliente(PortalPublicoFake::CLIENTE_INEXISTENTE);
    }

    public function test_m5_recibe_las_direcciones_con_la_principal_primero(): void
    {
        $direcciones = (new PortalPublicoFake)->listarDireccionesCliente(PortalPublicoFake::CLIENTE_CON_DIRECCIONES);

        $this->assertSame(PortalPublicoFake::DIRECCION_PRINCIPAL, $direcciones[0]['id_direccion_cliente']);
        $this->assertTrue($direcciones[0]['es_principal']);
        $this->assertCount(2, $direcciones);
    }

    public function test_m5_no_puede_usar_la_direccion_de_otro_cliente(): void
    {
        $this->expectException(DireccionNoEncontradaException::class);

        (new PortalPublicoFake)->obtenerDireccionCliente(
            PortalPublicoFake::CLIENTE_CON_DIRECCIONES,
            PortalPublicoFake::DIRECCION_DE_OTRO_CLIENTE,
        );
    }

    public function test_el_nucleo_obtiene_el_cliente_de_una_cuenta_y_err_004_si_no_tiene(): void
    {
        $fake = new PortalPublicoFake;

        $this->assertSame(PortalPublicoFake::CLIENTE_CON_DIRECCIONES, $fake->obtenerIdClientePorUsuario(PortalPublicoFake::USUARIO_CON_CLIENTE));

        $this->expectException(ClienteNoEncontradoException::class);
        $fake->obtenerIdClientePorUsuario(PortalPublicoFake::USUARIO_SIN_CLIENTE);
    }

    public function test_el_registro_rechaza_un_correo_repetido_sin_distinguir_mayusculas(): void
    {
        $this->expectException(CorreoYaRegistradoException::class);

        (new PortalPublicoFake)->registrarCliente([
            'nombre' => 'Otra',
            'apellidos' => 'Persona',
            'correo_electronico' => 'ANA.MORA@example.com',
            'contrasena' => 'secreta123',
        ]);
    }

    public function test_una_direccion_nueva_principal_deja_de_lado_la_anterior(): void
    {
        $fake = new PortalPublicoFake;

        $nueva = $fake->agregarDireccionCliente(PortalPublicoFake::CLIENTE_CON_DIRECCIONES, [
            'alias_direccion' => 'Familia',
            'nombre_destinatario' => 'Rosa Mora',
            'telefono_destinatario' => '8888-1111',
            'provincia' => 'Guanacaste',
            'canton' => 'Nicoya',
            'distrito' => 'Nicoya',
            'otras_senas' => 'Frente a la iglesia',
            'es_principal' => true,
        ]);

        $direcciones = $fake->listarDireccionesCliente(PortalPublicoFake::CLIENTE_CON_DIRECCIONES);

        $this->assertSame($nueva['id_direccion_cliente'], $direcciones[0]['id_direccion_cliente']);
        $this->assertCount(1, array_filter($direcciones, fn (array $direccion): bool => $direccion['es_principal']));
    }

    /**
     * Firmas de x-interfaz-php (públicas y de uso interno), por nombre de método.
     *
     * @return array<string, string>
     */
    private function firmasPublicadas(): array
    {
        $contrato = Yaml::parseFile(base_path('contracts/m1-portal/v1/openapi.yaml'))['x-interfaz-php'];
        $firmas = [];

        foreach ([...$contrato['metodos'], ...$contrato['metodos_de_uso_interno']['lista']] as $metodo) {
            $firma = preg_replace('/\s+/', ' ', $metodo['firma']);
            $firmas[strstr($firma, '(', true)] = $firma;
        }

        return $firmas;
    }

    /**
     * Firma del método con el mismo formato que el contrato:
     * nombre(tipo $param = valor, ...): retorno.
     */
    private function firma(ReflectionMethod $metodo): string
    {
        $parametros = array_map(function (ReflectionParameter $parametro): string {
            $texto = $this->tipo($parametro->getType()).' $'.$parametro->getName();

            if ($parametro->isDefaultValueAvailable()) {
                $texto .= ' = '.strtolower(var_export($parametro->getDefaultValue(), true));
            }

            return $texto;
        }, $metodo->getParameters());

        return $metodo->getName().'('.implode(', ', $parametros).'): '.$this->tipo($metodo->getReturnType());
    }

    private function tipo(?\ReflectionType $tipo): string
    {
        $this->assertInstanceOf(ReflectionNamedType::class, $tipo);

        return ($tipo->allowsNull() && $tipo->getName() !== 'mixed' ? '?' : '').$tipo->getName();
    }
}
