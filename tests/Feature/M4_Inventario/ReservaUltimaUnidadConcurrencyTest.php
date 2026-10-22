<?php

namespace Tests\Feature\M4_Inventario;

use Dotenv\Dotenv;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * PostgreSQL real y dos procesos independientes; no utiliza RefreshDatabase.
 * Conserva los registros como evidencia por la prohibición de borrar reservas.
 */
#[Group('m4-concurrencia')]
class ReservaUltimaUnidadConcurrencyTest extends TestCase
{
    public function test_solo_una_sesion_reserva_la_ultima_unidad(): void
    {
        if (getenv('M4_POC_ENABLED') !== '1') {
            $this->markTestSkipped('Habilitar M4_POC_ENABLED=1 en una base local exclusiva terminada en _m4_poc.');
        }

        $root = dirname(__DIR__, 3);
        $this->assertFileExists($root.'/app/Contracts/M4_Inventario/InventarioModuleInterface.php', 'Integrar primero los archivos de Jorge.');
        $env = Dotenv::parse(file_get_contents($root.'/.env'));
        $this->assertSame('pgsql', $env['DB_CONNECTION'] ?? null);
        $this->assertContains($env['DB_HOST'] ?? null, ['127.0.0.1', 'localhost']);
        $this->assertStringEndsWith('_m4_poc', $env['DB_DATABASE'] ?? '');
        $connection = [
            'driver' => 'pgsql', 'host' => $env['DB_HOST'], 'port' => $env['DB_PORT'] ?? '5432',
            'database' => $env['DB_DATABASE'], 'username' => $env['DB_USERNAME'],
            'password' => $env['DB_PASSWORD'] ?? '', 'charset' => 'utf8', 'prefix' => '',
            'search_path' => 'public', 'sslmode' => $env['DB_SSLMODE'] ?? 'prefer',
        ];
        $db = new PDO(
            "pgsql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']};sslmode={$connection['sslmode']}",
            $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->assertSame($connection['database'], $db->query('SELECT current_database()')->fetchColumn());
        $this->assertSame('activa', $db->query('SELECT codigo FROM estados_reserva WHERE id_estado_reserva = 1')->fetchColumn());
        $variation = $db->query('SELECT n FROM generate_series(1,1000) n WHERE NOT EXISTS (SELECT 1 FROM existencias e WHERE e.id_comercio = 1 AND e.id_variacion = n) ORDER BY n DESC LIMIT 1')->fetchColumn();
        $this->assertNotFalse($variation, 'Se agotaron las variaciones libres admitidas por CatalogoStub. Usar otra base exclusiva.');
        $insert = $db->prepare('INSERT INTO existencias (id_comercio,id_producto,id_variacion,id_ubicacion,cantidad_fisica,cantidad_reservada,cantidad_defectuosa) VALUES (1,1,?,1,1,0,0) RETURNING id_existencia');
        $insert->execute([(int) $variation]);
        $existencia = (int) $insert->fetchColumn();
        $keys = ['poc-'.bin2hex(random_bytes(12)), 'poc-'.bin2hex(random_bytes(12))];
        $processes = [];
        $observed = 0;

        try {
            $db->beginTransaction();
            $lock = $db->prepare('SELECT id_existencia FROM existencias WHERE id_existencia = ? FOR UPDATE');
            $lock->execute([$existencia]);
            $pid = (int) $db->query('SELECT pg_backend_pid()')->fetchColumn();
            foreach ($keys as $index => $key) {
                $process = new Process([PHP_BINARY, __DIR__.'/reserva_concurrente_worker.php', $root], $root, null, json_encode([
                    'connection' => $connection, 'variation' => (int) $variation, 'key' => $key,
                    'session' => 'm4-poc-'.$existencia.'-'.$index,
                ], JSON_THROW_ON_ERROR), 40);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 20;
            do {
                $db->query('SELECT pg_stat_clear_snapshot()');
                $waiting = $db->prepare(<<<'SQL'
WITH RECURSIVE esperando(pid) AS (
    SELECT pid FROM pg_stat_activity
    WHERE datname = current_database() AND CAST(? AS integer) = ANY(pg_blocking_pids(pid))
    UNION
    SELECT a.pid FROM pg_stat_activity a JOIN esperando e ON e.pid = ANY(pg_blocking_pids(a.pid))
    WHERE a.datname = current_database()
)
SELECT count(*) FROM pg_stat_activity a JOIN esperando e USING(pid)
WHERE a.application_name IN (?, ?)
SQL);
                $waiting->execute([$pid, 'm4-poc-'.$existencia.'-0', 'm4-poc-'.$existencia.'-1']);
                $observed = (int) $waiting->fetchColumn();
                if ($observed === 2 || ! $processes[0]->isRunning() || ! $processes[1]->isRunning()) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            $db->rollBack();
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), 'El proceso PHP no terminó correctamente. Revisar la integración de Jorge y los registros de Laravel.');
                $results[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
        $this->assertSame(2, $observed, 'No se observaron ambas sesiones esperando el bloqueo de la existencia.');
        $winners = array_values(array_filter($results, fn (array $r): bool => $r['result'] === 'reserva'));
        $losers = array_values(array_filter($results, fn (array $r): bool => $r['result'] === 'error'));
        $this->assertCount(1, $winners);
        $this->assertCount(1, $losers);
        $this->assertSame('ERR-M4-001', $losers[0]['codigo']);
        $this->assertSame(409, $losers[0]['http']);
        $this->assertSame([0, 0], $losers[0]['events']);
        $this->assertSame([1, 1], $winners[0]['events']);
        $reservation = $winners[0]['reserva'];
        $this->assertSame('activa', $reservation['estado_reserva']);
        $this->assertSame($existencia, $reservation['id_existencia']);
        $this->assertSame(1, $reservation['cantidad']);
        $stock = $db->prepare('SELECT cantidad_fisica,cantidad_reservada,cantidad_defectuosa,cantidad_fisica-cantidad_reservada-cantidad_defectuosa AS disponible FROM existencias WHERE id_existencia=?');
        $stock->execute([$existencia]);
        $this->assertSame([1.0, 1.0, 0.0, 0.0], array_map('floatval', $stock->fetch(PDO::FETCH_NUM)));
        $saved = $db->prepare('SELECT id_reserva,id_estado_reserva,cantidad,clave_idempotencia FROM reservas WHERE id_existencia=?');
        $saved->execute([$existencia]);
        $rows = $saved->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $rows);
        $this->assertSame($reservation['id_reserva'], $rows[0]['id_reserva']);
        $this->assertSame(1, (int) $rows[0]['id_estado_reserva']);
        $this->assertSame(1.0, (float) $rows[0]['cantidad']);
        $this->assertContains($rows[0]['clave_idempotencia'], $keys);
    }
}
