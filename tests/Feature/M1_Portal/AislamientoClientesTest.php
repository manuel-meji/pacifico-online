<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\ClienteNoAccesibleException;
use App\Contracts\Nucleo\ResuelveClienteInterface;
use App\Http\Middleware\EstablecerCliente;
use App\Models\M1_Portal\BusquedaUsuario;
use App\Models\Nucleo\Usuario;
use App\Services\M1_Portal\AccesoCliente;
use App\Services\M1_Portal\ClienteService;
use App\Services\M1_Portal\DTO\ClienteDTO;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\M1_Portal\DTO\DireccionClienteDTO;
use App\Services\M1_Portal\RegistroBusquedas;
use App\Services\M1_Portal\ResolvedorCliente;
use App\Services\Nucleo\ContextoCliente;
use Illuminate\Support\Facades\Route;
use Tests\Doubles\M1\IdentidadDoble;
use Tests\Doubles\M1\RegistroBusquedasEnMemoria;
use Tests\Feature\M1_Portal\Concerns\CreaTablasDeClientes;

/**
 * Tarea 23 del plan del PC4: aislamiento del núcleo en las rutas de clientes
 * y direcciones (RN-12, ADR-006). M1 entrega ResuelveClienteInterface, el
 * middleware EstablecerCliente fija ContextoCliente y AccesoCliente solo deja
 * pasar al cliente de ese contexto (RN-M1-16, ERR-003).
 *
 * Tablas en memoria y doble del núcleo: ver CreaTablasDeClientes.
 */
class AislamientoClientesTest extends PruebaM1
{
    use CreaTablasDeClientes;

    /** Rutas que exigen sesión y fijan el ámbito de cliente. */
    private const RUTAS_CON_SESION = [
        'api.m1.v1.clientes.sesion',
        'api.m1.v1.clientes.mostrar',
        'api.m1.v1.clientes.actualizar',
        'api.m1.v1.clientes.direcciones.listar',
        'api.m1.v1.clientes.direcciones.agregar',
        'api.m1.v1.clientes.direcciones.actualizar',
    ];

    /** Rutas públicas que fijan el ámbito si hay sesión de cliente. */
    private const RUTAS_PUBLICAS_CON_AMBITO = [
        'api.m1.v1.productos.busqueda',
        'api.m1.v1.productos.ficha',
    ];

    private int $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararClientes();

        $this->ana = app(ClienteService::class)->registrar(
            new ClienteDTO(null, 'Ana', 'Mora Rojas', 'ana@example.com', '8888-0000', '5-0123-0456', ''),
            new DireccionClienteDTO(null, null, 'Casa', 'Ana Mora', '8888-0000', 'Guanacaste', 'Liberia', 'Liberia', '200 m norte del parque', true),
            'secreta123',
        );
    }

    // ---------------------------------------------------------------
    // ResuelveClienteInterface (lo que M1 entrega al núcleo)
    // ---------------------------------------------------------------

    public function test_el_contenedor_entrega_la_implementacion_de_m1(): void
    {
        $this->assertInstanceOf(ResolvedorCliente::class, app(ResuelveClienteInterface::class));
    }

    public function test_devuelve_el_id_cliente_de_la_cuenta(): void
    {
        $this->assertSame($this->ana, app(ResuelveClienteInterface::class)->obtenerIdClientePorUsuario(IdentidadDoble::PRIMER_ID));
    }

    public function test_devuelve_null_si_la_cuenta_no_es_de_un_cliente(): void
    {
        $this->assertNull(app(ResuelveClienteInterface::class)->obtenerIdClientePorUsuario(9999));
    }

    // ---------------------------------------------------------------
    // Middleware en las rutas
    // ---------------------------------------------------------------

    public function test_las_rutas_de_clientes_y_direcciones_exigen_sesion_y_fijan_el_ambito(): void
    {
        foreach (self::RUTAS_CON_SESION as $nombre) {
            $middleware = Route::getRoutes()->getByName($nombre)->gatherMiddleware();

            $this->assertContains('auth:sanctum', $middleware, $nombre);
            $this->assertContains(EstablecerCliente::class, $middleware, $nombre);
            $this->assertLessThan(
                array_search(EstablecerCliente::class, $middleware, true),
                array_search('auth:sanctum', $middleware, true),
                "{$nombre}: la sesión se revisa antes de fijar el ámbito.",
            );
        }
    }

    public function test_la_busqueda_y_la_ficha_fijan_el_ambito_sin_exigir_sesion(): void
    {
        foreach (self::RUTAS_PUBLICAS_CON_AMBITO as $nombre) {
            $middleware = Route::getRoutes()->getByName($nombre)->gatherMiddleware();

            $this->assertContains(EstablecerCliente::class, $middleware, $nombre);
            $this->assertNotContains('auth:sanctum', $middleware, $nombre);
        }
    }

    public function test_el_registro_y_las_categorias_no_fijan_el_ambito(): void
    {
        foreach (['api.m1.v1.clientes.registrar', 'api.m1.v1.categorias'] as $nombre) {
            $this->assertNotContains(EstablecerCliente::class, Route::getRoutes()->getByName($nombre)->gatherMiddleware(), $nombre);
        }
    }

    public function test_una_peticion_con_sesion_de_cliente_queda_en_su_ambito(): void
    {
        $this->comoAna()->getJson('/api/m1/v1/clientes/sesion')->assertOk();

        $this->assertSame($this->ana, app(ContextoCliente::class)->idActual());
    }

    // ---------------------------------------------------------------
    // AccesoCliente usa el ámbito del núcleo (falla cerrado)
    // ---------------------------------------------------------------

    public function test_sin_ambito_de_cliente_ni_sus_propios_datos_son_accesibles(): void
    {
        // El núcleo no logra resolver el cliente: aunque Ana pida su propio
        // id_cliente, M1 no consulta clientes por su cuenta y niega el acceso.
        $this->app->instance(ResuelveClienteInterface::class, new class implements ResuelveClienteInterface
        {
            public function obtenerIdClientePorUsuario(int $idUsuario): ?int
            {
                return null;
            }
        });

        $this->comoAna()->getJson("/api/m1/v1/clientes/{$this->ana}")
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-003');

        $this->comoAna()->getJson("/api/m1/v1/clientes/{$this->ana}/direcciones")
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-003');
    }

    public function test_el_acceso_auditado_del_nucleo_no_abre_los_datos_de_clientes(): void
    {
        $contexto = new ContextoCliente;
        $contexto->concederAccesoAuditado();

        $this->expectException(ClienteNoAccesibleException::class);

        (new AccesoCliente($contexto))->exigirPropio($this->cuenta(9999), $this->ana);
    }

    public function test_acceso_cliente_compara_con_el_cliente_del_contexto(): void
    {
        $contexto = new ContextoCliente;
        $contexto->establecer($this->ana);
        $acceso = new AccesoCliente($contexto);

        $this->assertSame($this->ana, $acceso->idClienteDeSesion($this->cuenta(IdentidadDoble::PRIMER_ID)));

        $this->expectException(ClienteNoAccesibleException::class);
        $acceso->exigirPropio($this->cuenta(IdentidadDoble::PRIMER_ID), $this->ana + 1);
    }

    // ---------------------------------------------------------------
    // Búsqueda: se registra con el cliente de la sesión
    // ---------------------------------------------------------------

    public function test_la_busqueda_de_un_cliente_con_sesion_se_registra_con_su_id_cliente(): void
    {
        $registro = $this->registroQueAnotaElCliente();

        $this->comoAna()->getJson('/api/m1/v1/productos/busqueda?q=camisa')->assertOk();

        $this->assertSame([$this->ana], $registro->clientes);
    }

    public function test_la_busqueda_sin_sesion_se_registra_sin_cliente(): void
    {
        $registro = $this->registroQueAnotaElCliente();

        $this->getJson('/api/m1/v1/productos/busqueda?q=camisa')->assertOk();

        $this->assertSame([null], $registro->clientes);
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
     * Registro de búsquedas en memoria que anota el cliente del ámbito al
     * momento de registrar, como lo hace RegistroBusquedas con la base.
     */
    private function registroQueAnotaElCliente(): RegistroBusquedasEnMemoria
    {
        $registro = new class extends RegistroBusquedasEnMemoria
        {
            /** @var list<int|null> */
            public array $clientes = [];

            public function registrar(CriterioBusquedaDTO $criterio, int $cantidadResultados, ?string $tipoCategoria): BusquedaUsuario
            {
                $this->clientes[] = app(ContextoCliente::class)->idActual();

                return parent::registrar($criterio, $cantidadResultados, $tipoCategoria);
            }
        };

        $this->app->instance(RegistroBusquedas::class, $registro);

        return $registro;
    }
}
