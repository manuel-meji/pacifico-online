<?php

declare(strict_types=1);

use App\Events\M6_Pagos\ReembolsoProcesado;
use App\Services\M7_Contabilidad\IntegracionFinancieraM6Service;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require $argv[1].DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
$entrada = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$app = require $argv[1].DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => 'm7_concurrencia',
    'database.connections.m7_concurrencia' => $entrada['conexion'],
]);
DB::select("SELECT set_config('application_name', ?, false)", [$entrada['sesion']]);
DB::statement("SET statement_timeout = '30s'");
$numero = $entrada['numero'];
$evento = new ReembolsoProcesado(
    sprintf('22222222-2222-4222-8222-%012d', $numero),
    8942, 1001, 11, $numero, 70.0, 40 + $numero, '2026-11-12T12:00:00Z', 7,
);

try {
    $app->make(ContextoComercio::class)->ejecutarComoSistema(
        fn () => $app->make(IntegracionFinancieraM6Service::class)->procesarReembolso($evento)
    );
    $resultado = ['estado' => 'registrado'];
} catch (LogicException $error) {
    $resultado = ['estado' => 'error', 'mensaje' => $error->getMessage()];
}

echo json_encode($resultado, JSON_THROW_ON_ERROR);
