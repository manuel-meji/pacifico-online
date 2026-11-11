<?php

namespace Tests\Feature\M4_Inventario\Aceptacion;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Events\M6_Pagos\PagoRechazado;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Feature\M4_Inventario\EsquemaInventarioPrueba;
use Tests\TestCase;

/**
 * Prueba de aceptación 1 (CU-M4-06 y CU-M4-07; RN-04, RN-M4-001, RN-M4-006 y ADR-002):
 * dos sesiones de compra quieren la última unidad de una variación. Cada una reserva y,
 * si obtiene la unidad, confirma la reserva con su pedido, como hace M5. Solo una lo
 * logra; la otra recibe ERR-M4-001 y el disponible nunca queda negativo. Si el pago de
 * la ganadora se rechaza, la unidad vuelve y la otra sesión puede comprarla.
 *
 * La versión secuencial corre siempre (SQLite). La concurrente usa dos procesos PHP sobre
 * PostgreSQL local, que es donde existe SELECT … FOR UPDATE; se omite si el .env no apunta a
 * un PostgreSQL local y nunca corre contra la base compartida (Supabase).
 */
#[Group('m4-concurrencia')]
class UltimaUnidadDosSesionesTest extends TestCase
{
    use EsquemaInventarioPrueba;

    public function test_dos_sesiones_por_la_ultima_unidad_solo_una_confirma(): void
    {
        config(['integraciones.m4.nucleo.modo' => 'real']);
        $this->crearEsquemaNucleo();
        $this->crearEsquemaInventario();
        $this->existencia(['id_variacion' => 10, 'id_ubicacion' => 1, 'cantidad_fisica' => 1]);
        $inventario = app(InventarioModuleInterface::class);

        // Sesión A: reserva y confirma su pedido 7001.
        $a = $inventario->reservarExistencias(1, 10, 1, null, 'sesion-a', 15);
        $this->assertSame('confirmada', $inventario->confirmarReserva($a['id_reserva'], 7001, 7101)['estado_reserva']);

        // Sesión B: no hay unidad disponible.
        try {
            $inventario->reservarExistencias(1, 10, 1, null, 'sesion-b', 15);
            $this->fail('La sesión B no debía obtener la unidad.');
        } catch (ErrorInventarioException $e) {
            $this->assertSame(['ERR-M4-001', 409], [$e->codigo, $e->http]);
        }
        $this->assertSame(['cantidad_disponible' => 0, 'estado_disponibilidad' => 'agotado'],
            array_intersect_key($inventario->consultarStockDisponible(1, 10), ['cantidad_disponible' => 0, 'estado_disponibilidad' => 0]));

        // M6 rechaza el pago de A: la unidad vuelve y B la compra.
        event(new PagoRechazado('5a3c2f00-0000-4000-8000-000000000001', 7001, 1, 'Tarjeta rechazada', now()->toIso8601String()));
        $this->assertSame('liberada', $inventario->obtenerReserva($a['id_reserva'])['estado_reserva']);
        $b = $inventario->reservarExistencias(1, 10, 1, null, 'sesion-b', 15);
        $this->assertSame('confirmada', $inventario->confirmarReserva($b['id_reserva'], 7002, 7102)['estado_reserva']);
        $this->assertSame([1, 1], array_values(array_map('intval', (array) DB::table('existencias')->first(['cantidad_fisica', 'cantidad_reservada']))));
    }

    public function test_dos_sesiones_simultaneas_en_postgresql(): void
    {
        $raiz = dirname(__DIR__, 4);
        $env = is_file($raiz.'/.env') ? Dotenv::parse(file_get_contents($raiz.'/.env')) : [];
        $host = (string) ($env['DB_HOST'] ?? '');

        if (($env['DB_CONNECTION'] ?? null) !== 'pgsql' || (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true) && ! str_starts_with($host, '/'))) {
            $this->markTestSkipped('Requiere PostgreSQL local en el .env (DB_CONNECTION=pgsql, DB_HOST=127.0.0.1); nunca corre contra la base compartida.');
        }

        $conexion = [
            'driver' => 'pgsql', 'host' => $host, 'port' => $env['DB_PORT'] ?? '5432', 'database' => $env['DB_DATABASE'],
            'username' => $env['DB_USERNAME'], 'password' => $env['DB_PASSWORD'] ?? '', 'charset' => 'utf8', 'prefix' => '',
            'search_path' => 'public', 'sslmode' => $env['DB_SSLMODE'] ?? 'prefer',
        ];
        $db = new PDO("pgsql:host={$host};port={$conexion['port']};dbname={$conexion['database']};sslmode={$conexion['sslmode']}",
            $conexion['username'], $conexion['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $variacion = (int) $db->query('SELECT n FROM generate_series(1,1000) n WHERE NOT EXISTS (SELECT 1 FROM existencias e WHERE e.id_comercio = 1 AND e.id_variacion = n) ORDER BY n DESC LIMIT 1')->fetchColumn();
        $this->assertGreaterThan(0, $variacion, 'No quedan variaciones libres en el rango de CatalogoStub.');
        $alta = $db->prepare('INSERT INTO existencias (id_comercio,id_producto,id_variacion,id_ubicacion,cantidad_fisica,cantidad_reservada) VALUES (1,1,?,1,1,0) RETURNING id_existencia');
        $alta->execute([$variacion]);
        $existencia = (int) $alta->fetchColumn();
        $prefijo = "m4-acep-{$existencia}-";
        $procesos = [];

        try {
            // Se retiene la fila para que las dos sesiones lleguen a la vez al bloqueo.
            $db->beginTransaction();
            $db->prepare('SELECT 1 FROM existencias WHERE id_existencia = ? FOR UPDATE')->execute([$existencia]);
            $php = (new PhpExecutableFinder)->find(false) ?: PHP_BINARY;

            foreach ([1, 2] as $n) {
                $proceso = new Process([$php, __DIR__.'/ultima_unidad_sesion_worker.php', $raiz], $raiz, null, json_encode([
                    'connection' => $conexion + ['application_name' => $prefijo.$n], 'session' => $prefijo.$n,
                    'variation' => $variacion, 'key' => "acep-{$existencia}-{$n}", 'pedido' => 8000 + $n, 'subpedido' => 8100 + $n,
                ], JSON_THROW_ON_ERROR), 60);
                $proceso->start();
                $procesos[] = $proceso;
            }

            $limite = microtime(true) + 30;
            do {
                $db->query('SELECT pg_stat_clear_snapshot()');
                $espera = $db->prepare("SELECT count(*) FROM pg_stat_activity WHERE application_name LIKE ? AND wait_event_type = 'Lock'");
                $espera->execute([$prefijo.'%']);
                $esperando = (int) $espera->fetchColumn();
                usleep(20000);
            } while ($esperando < 2 && microtime(true) < $limite);

            $db->rollBack();
            $resultados = array_map(function (Process $p): array {
                $this->assertSame(0, $p->wait(), $p->getErrorOutput());

                return json_decode($p->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }, $procesos);
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }

        $this->assertSame(2, $esperando, 'Las dos sesiones debían esperar el mismo bloqueo.');
        $confirmadas = array_values(array_filter($resultados, fn (array $r): bool => $r['result'] === 'confirmada'));
        $rechazadas = array_values(array_filter($resultados, fn (array $r): bool => $r['result'] === 'error'));
        $this->assertCount(1, $confirmadas);
        $this->assertCount(1, $rechazadas);
        $this->assertSame(['ERR-M4-001', 409], [$rechazadas[0]['codigo'], $rechazadas[0]['http']]);
        $this->assertSame('confirmada', $confirmadas[0]['reserva']['estado_reserva']);

        $saldo = $db->prepare('SELECT cantidad_fisica, cantidad_reservada FROM existencias WHERE id_existencia = ?');
        $saldo->execute([$existencia]);
        $this->assertSame([1, 1], array_map('intval', $saldo->fetch(PDO::FETCH_NUM)));
    }
}
