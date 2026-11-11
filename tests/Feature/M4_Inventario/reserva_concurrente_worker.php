<?php

use App\Contracts\M4_Inventario\Dependencias\CatalogoDependencia;
use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Events\M4_Inventario\ExistenciaReservada;
use App\Services\M4_Inventario\Dobles\CatalogoStub;
use App\Services\M4_Inventario\Dobles\ComerciosStub;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require $argv[1].'/vendor/autoload.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'm4_concurrencia', 'database.connections.m4_concurrencia' => $input['connection']]);
$app->bind(CatalogoDependencia::class, CatalogoStub::class);
$app->bind(ComerciosDependencia::class, ComerciosStub::class);
DB::select("SELECT set_config('application_name', ?, false)", [$input['session']]);
DB::statement("SET statement_timeout = '30s'");
Event::fake([ExistenciaReservada::class, ExistenciaAgotada::class]);
try {
    $reserva = $app->make(InventarioModuleInterface::class)->reservarExistencias(1, $input['variation'], 1, 1, $input['key'], 15);
    $result = ['result' => 'reserva', 'reserva' => $reserva];
} catch (ErrorInventarioException $error) {
    $result = ['result' => 'error', 'codigo' => $error->codigo, 'http' => $error->http];
}
$result['events'] = [Event::dispatched(ExistenciaReservada::class)->count(), Event::dispatched(ExistenciaAgotada::class)->count()];
echo json_encode($result, JSON_THROW_ON_ERROR);
