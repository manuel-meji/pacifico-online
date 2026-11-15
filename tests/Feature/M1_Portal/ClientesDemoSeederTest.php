<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\CorreoYaRegistradoException;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Models\M1_Portal\Cliente;
use App\Models\M1_Portal\DireccionCliente;
use App\Models\Nucleo\Usuario;
use App\Services\Nucleo\ServicioIdentidad;
use Database\Seeders\M1_Portal\ClientesDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\Feature\M1_Portal\Concerns\CreaTablasDeClientes;

/**
 * ClientesDemoSeeder: el cliente del escenario 1 (colectivo del PC4, 2.2).
 *
 * A diferencia de las demás pruebas de clientes de M1, aquí el núcleo es el real: la
 * cuenta se crea en la tabla usuarios con ServicioIdentidad, para comprobar el rol y el
 * inicio de sesión. Las tablas de M1 son la versión simple de CreaTablasDeClientes.
 */
class ClientesDemoSeederTest extends PruebaM1
{
    use CreaTablasDeClientes;
    use RefreshDatabase;

    private const CONTRASENA = 'clave-de-demostracion-123';

    /**
     * Las mismas migraciones del núcleo que usan sus pruebas (ServicioIdentidadTest).
     *
     * @var list<string>
     */
    private const MIGRACIONES = [
        'database/migrations/Nucleo/2026_09_30_000001_crear_tabla_usuarios.php',
        'database/migrations/Nucleo/2026_09_30_000002_crear_tabla_bitacora_auditoria.php',
        'database/migrations/Nucleo/2026_09_30_000003_crear_tablas_sesiones_y_restablecimientos.php',
        'database/migrations/Nucleo/2026_09_30_000004_crear_tablas_trabajos_cola.php',
        'database/migrations/Nucleo/2026_11_02_191532_agregar_inmutabilidad_a_bitacora_auditoria.php',
    ];

    /** @return array<string, mixed> */
    protected function migrateUsing(): array
    {
        return ['--path' => self::MIGRACIONES, '--seed' => false];
    }

    /** @return array<string, mixed> */
    protected function migrateFreshUsing(): array
    {
        return ['--path' => self::MIGRACIONES, '--drop-views' => false, '--drop-types' => false, '--seed' => false];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararClientes();

        // prepararClientes deja el doble del núcleo; esta prueba necesita el servicio real.
        $this->app->bind(ServicioIdentidadInterface::class, ServicioIdentidad::class);

        config(['nucleo.demo.contrasena' => self::CONTRASENA]);
    }

    public function test_crea_la_cuenta_con_rol_cliente(): void
    {
        $this->seed(ClientesDemoSeeder::class);

        $this->assertSame(1, Usuario::query()->count());

        $usuario = Usuario::query()->where('correo', ClientesDemoSeeder::CORREO)->firstOrFail();
        $this->assertSame('Jorge Araya', $usuario->nombre);
        $this->assertSame(RolUsuario::Cliente, $usuario->rol);
        $this->assertTrue($usuario->activo);
        $this->assertNull($usuario->id_comercio);
    }

    public function test_crea_el_cliente_con_su_cedula_y_una_sola_direccion_principal(): void
    {
        $this->seed(ClientesDemoSeeder::class);

        $this->assertSame(1, Cliente::query()->count());

        $cliente = Cliente::query()->where('correo_electronico', 'jorge.araya.demopacificos@gmail.com')->firstOrFail();
        $usuario = Usuario::query()->where('correo', 'jorge.araya.demopacificos@gmail.com')->firstOrFail();
        $this->assertSame($usuario->id_usuario, $cliente->id_usuario);
        $this->assertSame('1-1111-1111', $cliente->cedula);
        $this->assertSame('Jorge', $cliente->nombre);
        $this->assertSame('Araya', $cliente->apellidos);
        $this->assertSame('8888-1111', $cliente->telefono);
        $this->assertSame(Cliente::ESTADO_ACTIVO, $cliente->estado_cliente);

        $direcciones = DireccionCliente::query()->where('id_cliente', $cliente->id_cliente)->get();
        $this->assertCount(1, $direcciones);
        $this->assertSame(1, DireccionCliente::query()->count());

        $direccion = $direcciones[0];
        $this->assertTrue($direccion->es_principal);
        $this->assertSame('Casa', $direccion->alias_direccion);
        $this->assertSame('Jorge Araya', $direccion->nombre_destinatario);
        $this->assertSame('8888-1111', $direccion->telefono_destinatario);
        $this->assertSame('Guanacaste', $direccion->provincia);
        $this->assertSame('Liberia', $direccion->canton);
        $this->assertSame('Liberia', $direccion->distrito);
        $this->assertSame('Barrio Capulín', $direccion->otras_senas);
    }

    public function test_la_contrasena_sirve_para_iniciar_sesion(): void
    {
        $this->seed(ClientesDemoSeeder::class);

        $usuario = Usuario::query()->where('correo', ClientesDemoSeeder::CORREO)->firstOrFail();

        // Se guarda cifrada una sola vez: el hash corresponde a la contraseña en texto plano.
        $this->assertNotSame(self::CONTRASENA, $usuario->contrasena);
        $this->assertTrue(Hash::check(self::CONTRASENA, $usuario->contrasena));

        $this->postJson('/login', ['correo' => ClientesDemoSeeder::CORREO, 'contrasena' => self::CONTRASENA])
            ->assertOk()
            ->assertJsonPath('user.rol', 'cliente')
            ->assertJsonPath('user.id_comercio', null);

        $this->assertAuthenticatedAs($usuario, 'web');
    }

    public function test_ejecutarlo_dos_veces_no_duplica_nada(): void
    {
        $this->seed(ClientesDemoSeeder::class);
        $antes = $this->filas();

        // La segunda vez ni siquiera cambia la contraseña, aunque DEMO_CONTRASENA sea otra.
        config(['nucleo.demo.contrasena' => 'otra-clave-distinta-456']);
        $this->seed(ClientesDemoSeeder::class);

        $this->assertSame($antes, $this->filas());
        $this->assertSame(1, Usuario::query()->count());
        $this->assertSame(1, Cliente::query()->count());
        $this->assertSame(1, DireccionCliente::query()->count());
        $this->assertTrue(Hash::check(
            self::CONTRASENA,
            Usuario::query()->where('correo', ClientesDemoSeeder::CORREO)->firstOrFail()->contrasena,
        ));
    }

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['   '])]
    public function test_sin_demo_contrasena_lanza_la_excepcion_y_no_crea_nada(?string $valor): void
    {
        config(['nucleo.demo.contrasena' => $valor]);

        try {
            $this->seed(ClientesDemoSeeder::class);
            $this->fail('Se esperaba una RuntimeException por la falta de DEMO_CONTRASENA.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ClientesDemoSeeder', $e->getMessage());
            $this->assertStringContainsString('DEMO_CONTRASENA', $e->getMessage());
        }

        $this->assertNoSeCreoNada();
    }

    public function test_con_una_contrasena_mas_corta_que_el_minimo_del_nucleo_lanza_la_excepcion_y_no_crea_nada(): void
    {
        config(['nucleo.demo.contrasena' => str_repeat('a', ServicioIdentidad::LARGO_MINIMO_CONTRASENA - 1)]);

        try {
            $this->seed(ClientesDemoSeeder::class);
            $this->fail('Se esperaba una RuntimeException por la contraseña corta.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('DEMO_CONTRASENA', $e->getMessage());
            $this->assertStringContainsString('al menos '.ServicioIdentidad::LARGO_MINIMO_CONTRASENA.' caracteres', $e->getMessage());
        }

        $this->assertNoSeCreoNada();
    }

    public function test_si_la_cuenta_existe_en_el_nucleo_sin_cliente_falla_con_un_mensaje_claro(): void
    {
        app(ServicioIdentidadInterface::class)->crearCuenta([
            'nombre' => 'Cuenta previa',
            'correo' => ClientesDemoSeeder::CORREO,
            'contrasena' => 'otra-clave-previa',
        ], RolUsuario::Cliente);

        try {
            $this->seed(ClientesDemoSeeder::class);
            $this->fail('Se esperaba una RuntimeException por la cuenta que ya existe en el núcleo.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(ClientesDemoSeeder::CORREO, $e->getMessage());
            $this->assertStringContainsString('ya tiene una cuenta en el núcleo', $e->getMessage());
            $this->assertInstanceOf(CorreoYaRegistradoException::class, $e->getPrevious());
        }

        // La cuenta previa queda intacta y M1 no crea el cliente ni la dirección.
        $this->assertSame(1, Usuario::query()->count());
        $this->assertSame('Cuenta previa', Usuario::query()->firstOrFail()->nombre);
        $this->assertSame(0, Cliente::query()->count());
        $this->assertSame(0, DireccionCliente::query()->count());
    }

    private function assertNoSeCreoNada(): void
    {
        $this->assertSame(0, Usuario::query()->count());
        $this->assertSame(0, Cliente::query()->count());
        $this->assertSame(0, DireccionCliente::query()->count());
    }

    /**
     * Identificadores y marcas de tiempo de todo lo que crea el seeder.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function filas(): array
    {
        return [
            'usuarios' => Usuario::query()->orderBy('id_usuario')->get(['id_usuario', 'correo', 'rol'])->toArray(),
            'clientes' => Cliente::query()->orderBy('id_cliente')->get(['id_cliente', 'id_usuario', 'cedula', 'updated_at'])->toArray(),
            'direcciones' => DireccionCliente::query()->orderBy('id_direccion_cliente')->get(['id_direccion_cliente', 'id_cliente', 'es_principal', 'updated_at'])->toArray(),
        ];
    }
}
