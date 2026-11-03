<?php

declare(strict_types=1);

namespace Tests\Feature\Nucleo;

use App\Contracts\Nucleo\ResuelveClienteInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Http\Middleware\EstablecerCliente;
use App\Http\Middleware\EstablecerComercio;
use App\Models\Nucleo\Concerns\PerteneceACliente;
use App\Models\Nucleo\Concerns\PerteneceAComercio;
use App\Models\Nucleo\Usuario;
use App\Services\Nucleo\ContextoCliente;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AislamientoPorAmbitoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('pruebas_aislamiento', function ($tabla): void {
            $tabla->id();
            $tabla->unsignedBigInteger('id_comercio')->nullable();
            $tabla->unsignedBigInteger('id_cliente')->nullable();
            $tabla->string('nombre');
        });

        DB::table('pruebas_aislamiento')->insert([
            ['id_comercio' => 1, 'id_cliente' => 1, 'nombre' => 'comercio1_cliente1'],
            ['id_comercio' => 2, 'id_cliente' => 2, 'nombre' => 'comercio2_cliente2'],
        ]);
    }

    private function modeloComercio(): Model
    {
        return new class extends Model
        {
            use PerteneceAComercio;

            protected $table = 'pruebas_aislamiento';

            protected $guarded = [];

            public $timestamps = false;
        };
    }

    private function modeloCliente(): Model
    {
        return new class extends Model
        {
            use PerteneceACliente;

            protected $table = 'pruebas_aislamiento';

            protected $guarded = [];

            public $timestamps = false;
        };
    }

    private function contextoComercio(): ContextoComercio
    {
        return app(ContextoComercio::class);
    }

    private function contextoCliente(): ContextoCliente
    {
        return app(ContextoCliente::class);
    }

    public function test_sin_ambito_no_devuelve_registros(): void
    {
        $this->assertSame(0, $this->modeloComercio()->newQuery()->count());
        $this->assertSame(0, $this->modeloCliente()->newQuery()->count());
    }

    public function test_comercio_solo_ve_sus_registros(): void
    {
        $this->contextoComercio()->establecer(1);

        $nombres = $this->modeloComercio()->newQuery()->pluck('nombre')->all();

        $this->assertSame(['comercio1_cliente1'], $nombres);
    }

    public function test_cliente_solo_ve_sus_registros(): void
    {
        $this->contextoCliente()->establecer(2);

        $nombres = $this->modeloCliente()->newQuery()->pluck('nombre')->all();

        $this->assertSame(['comercio2_cliente2'], $nombres);
    }

    public function test_crear_asigna_el_comercio_del_ambito(): void
    {
        $this->contextoComercio()->establecer(1);

        $modelo = $this->modeloComercio();
        $modelo->nombre = 'nuevo';
        $modelo->save();

        $this->assertSame(1, (int) $modelo->getAttribute('id_comercio'));
    }

    public function test_crear_en_otro_comercio_responde_err_003(): void
    {
        $this->contextoComercio()->establecer(1);

        $modelo = $this->modeloComercio();
        $modelo->nombre = 'intruso';
        $modelo->id_comercio = 2;

        $this->assertErrorNoAccesible(fn () => $modelo->save());
    }

    public function test_mover_registro_a_otro_comercio_responde_err_003(): void
    {
        $this->contextoComercio()->establecer(1);

        $registro = $this->modeloComercio()->newQuery()->firstOrFail();
        $registro->id_comercio = 2;

        $this->assertErrorNoAccesible(fn () => $registro->save());
    }

    public function test_modo_sistema_no_filtra_y_luego_restaura_el_filtro(): void
    {
        $contexto = $this->contextoComercio();
        $contexto->establecer(1);

        $durante = $contexto->ejecutarComoSistema(
            fn () => $this->modeloComercio()->newQuery()->count()
        );

        $this->assertSame(2, $durante);
        $this->assertSame(1, $this->modeloComercio()->newQuery()->count());
    }

    public function test_establecer_comercio_toma_el_comercio_del_usuario(): void
    {
        $request = Request::create('/prueba');
        $request->setUserResolver(fn () => new Usuario(['id_comercio' => 2, 'activo' => true]));

        (new EstablecerComercio)->handle($request, fn () => response('ok'));

        $this->assertSame(2, $this->contextoComercio()->idActual());
    }

    public function test_usuario_sin_comercio_no_establece_ambito(): void
    {
        $request = Request::create('/prueba');
        $request->setUserResolver(fn () => new Usuario(['id_comercio' => null, 'activo' => true]));

        (new EstablecerComercio)->handle($request, fn () => response('ok'));

        $this->assertNull($this->contextoComercio()->idActual());
    }

    public function test_establecer_cliente_usa_el_contrato_de_m1(): void
    {
        $this->app->instance(ResuelveClienteInterface::class, new class implements ResuelveClienteInterface
        {
            public function obtenerIdClientePorUsuario(int $idUsuario): ?int
            {
                return $idUsuario === 7 ? 1 : null;
            }
        });

        $request = Request::create('/prueba');
        $usuario = new Usuario(['activo' => true]);
        $usuario->id_usuario = 7;
        $request->setUserResolver(fn () => $usuario);

        (new EstablecerCliente)->handle($request, fn () => response('ok'));

        $this->assertSame(1, $this->contextoCliente()->idActual());
    }

    private function assertErrorNoAccesible(callable $operacion): void
    {
        try {
            $operacion();
            $this->fail('Se esperaba ERR-003 y no se lanzó ninguna excepción.');
        } catch (ErrorNucleoException $e) {
            $this->assertSame('ERR-003', $e->getCodigo());
            $this->assertSame(404, $e->getCodigoHttp());
        }
    }
}