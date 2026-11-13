<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Models\M2_Comercios\Comercio;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Services\M2_Comercios\ComerciosService;
use App\Services\M5_Pedidos\Dobles\PortalStub;
use App\Services\M5_Pedidos\PedidosService;
use Database\Seeders\M2_Comercios\ComerciosDemoSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Doubles\M5\CatalogoPreciosFijos;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

class TarifasEnvioDemoTest extends TestCase
{
    use PreparaM2;

    #[TestWith(['3-101-100001', 2500.0, 3500.0])]
    #[TestWith(['3-101-100002', 2500.0, 3500.0])]
    #[TestWith(['3-101-100003', 2500.0, 3500.0])]
    #[TestWith(['3-101-100004', 3500.0, 2500.0])]
    #[TestWith(['3-101-100005', 2500.0, 1500.0])]
    public function test_seeder_conserva_las_tarifas_por_comercio_y_zona_al_repetirse(string $cedula, float $costoGam, float $costoResto): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->seed(ComerciosDemoSeeder::class);
        $comercio = Comercio::query()->where('cedula_juridica', $cedula)->firstOrFail();
        $servicio = app(ComerciosService::class);
        $antes = $servicio->obtenerPoliticasEnvio($comercio->id_comercio);

        $this->seed(ComerciosDemoSeeder::class);
        $resultado = $servicio->obtenerPoliticasEnvio($comercio->id_comercio);

        $this->assertSame([
            'id_comercio' => $comercio->id_comercio,
            'politicas' => [['tipo' => 'envio', 'descripcion' => 'Envío a todo el país; el costo depende de la zona.']],
            'tarifas' => [
                ['zona' => 'GAM', 'costo' => $costoGam, 'moneda' => 'CRC'],
                ['zona' => 'Resto del país', 'costo' => $costoResto, 'moneda' => 'CRC'],
            ],
        ], $resultado);
        $this->assertSame($antes, $resultado);
        $this->assertDatabaseCount('politicas_comercio', 5);
    }

    public function test_m5_consume_las_tarifas_reales_de_m2_para_gam_y_liberia(): void
    {
        $this->seed(ComerciosDemoSeeder::class);
        $deportes = Comercio::query()->where('cedula_juridica', '3-101-100004')->firstOrFail();
        $cafe = Comercio::query()->where('cedula_juridica', '3-101-100005')->firstOrFail();
        $catalogo = new CatalogoPreciosFijos([
            401 => ['precio' => 1000, 'id_comercio' => $deportes->id_comercio],
            501 => ['precio' => 1000, 'id_comercio' => $cafe->id_comercio],
        ]);
        $carrito = new Carrito;
        $carrito->setRelation('items', new Collection([
            new ItemCarrito(['id_variacion' => 401, 'cantidad' => 1]),
            new ItemCarrito(['id_variacion' => 501, 'cantidad' => 1]),
        ]));
        $m2 = app(ComerciosService::class);
        $m5 = new PedidosService;
        $portal = new PortalStub;

        $gam = $m5->calcularDesgloseCarrito($carrito, $catalogo, $m2, $portal, 'GAM');
        $liberia = $m5->calcularDesgloseCarrito($carrito, $catalogo, $m2, $portal, [
            'provincia' => 'Guanacaste', 'canton' => 'Liberia', 'distrito' => 'Liberia',
        ]);

        $this->assertSame(3500.0, $gam['grupos_comercio'][$deportes->id_comercio]['costo_envio']);
        $this->assertSame(2500.0, $gam['grupos_comercio'][$cafe->id_comercio]['costo_envio']);
        $this->assertSame(6000.0, $gam['resumen']['costo_envio']);
        $this->assertSame(2500.0, $liberia['grupos_comercio'][$deportes->id_comercio]['costo_envio']);
        $this->assertSame(1500.0, $liberia['grupos_comercio'][$cafe->id_comercio]['costo_envio']);
        $this->assertSame(4000.0, $liberia['resumen']['costo_envio']);
    }
}
