<?php

// Una «sesión» de compra de la prueba de aceptación 1: reserva la última unidad y,
// si la obtiene, confirma la reserva con su pedido (como M5 al confirmar el pedido).

use App\Contracts\M4_Inventario\Dependencias\CatalogoDependencia;
use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Services\M4_Inventario\Dobles\CatalogoStub;
use App\Services\M4_Inventario\Dobles\ComerciosStub;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require $argv[1].'/vendor/autoload.php';
$entrada = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => 'm4_aceptacion',
    'database.connections.m4_aceptacion' => $entrada['connection'],
    'integraciones.m4.nucleo.modo' => 'real',
]);
$app->bind(CatalogoDependencia::class, CatalogoStub::class);
$app->bind(ComerciosDependencia::class, ComerciosStub::class);
DB::select("SELECT set_config('application_name', ?, false)", [$entrada['session']]);
DB::statement("SET statement_timeout = '30s'");
Event::fake();

$inventario = $app->make(InventarioModuleInterface::class);

try {
    $reserva = $inventario->reservarExistencias(1, $entrada['variation'], 1, 1, $entrada['key'], 15);
    $confirmada = $inventario->confirmarReserva($reserva['id_reserva'], $entrada['pedido'], $entrada['subpedido']);
    $resultado = ['result' => 'confirmada', 'reserva' => $confirmada];
} catch (ErrorInventarioException $error) {
    $resultado = ['result' => 'error', 'codigo' => $error->codigo, 'http' => $error->http];
}

echo json_encode($resultado, JSON_THROW_ON_ERROR);
