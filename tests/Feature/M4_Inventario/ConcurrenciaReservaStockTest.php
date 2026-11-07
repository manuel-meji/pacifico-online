<?php

namespace Tests\Feature\M4_Inventario;

use Dotenv\Dotenv;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * ESC-01 / RN-04 / ADR-002: diez solicitudes simultáneas confirman la última
 * unidad de una variación; solo una obtiene la reserva.
 *
 * Es la prueba que cita el PC3 colectivo (tests/Feature/M4_Inventario/ConcurrenciaReservaStockTest.php).
 * Usa PostgreSQL real y diez procesos PHP independientes, porque el bloqueo de
 * filas (SELECT … FOR UPDATE) no existe en SQLite. Toma la conexión PostgreSQL
 * del archivo .env; solo se omite cuando el .env no apunta a un PostgreSQL local
 * (127.0.0.1, localhost o socket), y en ese caso lo dice en el mensaje. Nunca corre
 * contra la base compartida (Supabase). No usa RefreshDatabase: deja la existencia y
 * la reserva como evidencia, porque el trigger de reservas prohíbe borrarlas.
 *
 * Para observar la carrera de verdad, la prueba bloquea primero la fila de la
 * existencia, espera a que los diez procesos estén detenidos en ese bloqueo y
 * recién entonces lo suelta.
 */
#[Group('m4-concurrencia')]
class ConcurrenciaReservaStockTest extends TestCase
{
    private const SOLICITUDES = 10;

    public function test_diez_solicitudes_simultaneas_solo_una_reserva_la_ultima_unidad(): void
    {
        $root = dirname(__DIR__, 3);
        $env = is_file($root.'/.env') ? Dotenv::parse(file_get_contents($root.'/.env')) : [];

        if (($env['DB_CONNECTION'] ?? null) !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL en el .env (DB_CONNECTION=pgsql): SQLite no tiene bloqueo de filas.');
        }

        $this->assertNotSame('production', $env['APP_ENV'] ?? null, 'No ejecutar contra producción.');

        // La prueba deja filas en existencias y reservas (el trigger impide borrarlas): nunca en la base compartida.
        $host = (string) ($env['DB_HOST'] ?? '');
        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true) && ! str_starts_with($host, '/')) {
            $this->markTestSkipped("Requiere PostgreSQL local (DB_HOST=127.0.0.1): no corre contra la base compartida ({$host}).");
        }

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
        $this->assertSame('activa', $db->query('SELECT codigo FROM estados_reserva WHERE id_estado_reserva = 1')->fetchColumn(), 'Faltan las migraciones de M4.');

        // Variación libre del comercio 1 dentro del rango que admite CatalogoStub.
        $variacion = $db->query('SELECT n FROM generate_series(1,1000) n WHERE NOT EXISTS (SELECT 1 FROM existencias e WHERE e.id_comercio = 1 AND e.id_variacion = n) ORDER BY n DESC LIMIT 1')->fetchColumn();
        $this->assertNotFalse($variacion, 'No quedan variaciones libres en el rango de CatalogoStub.');
        $insert = $db->prepare('INSERT INTO existencias (id_comercio,id_producto,id_variacion,id_ubicacion,cantidad_fisica,cantidad_reservada) VALUES (1,1,?,1,1,0) RETURNING id_existencia');
        $insert->execute([(int) $variacion]);
        $existencia = (int) $insert->fetchColumn();

        $prefijo = 'm4-conc-'.$existencia.'-';
        $claves = [];
        $procesos = [];
        $esperando = 0;

        try {
            $db->beginTransaction();
            $db->prepare('SELECT id_existencia FROM existencias WHERE id_existencia = ? FOR UPDATE')->execute([$existencia]);
            $pid = (int) $db->query('SELECT pg_backend_pid()')->fetchColumn();

            $php = (new PhpExecutableFinder)->find(false) ?: PHP_BINARY;
            for ($i = 0; $i < self::SOLICITUDES; $i++) {
                $claves[$i] = 'conc-'.bin2hex(random_bytes(12));
                $proceso = new Process([$php, __DIR__.'/reserva_concurrente_worker.php', $root], $root, null, json_encode([
                    'connection' => $connection + ['application_name' => $prefijo.$i],
                    'variation' => (int) $variacion, 'key' => $claves[$i], 'session' => $prefijo.$i,
                ], JSON_THROW_ON_ERROR), 60);
                $proceso->start();
                $procesos[] = $proceso;
            }

            $limite = microtime(true) + 40;
            do {
                $db->query('SELECT pg_stat_clear_snapshot()');
                $consulta = $db->prepare(<<<'SQL'
WITH RECURSIVE esperando(pid) AS (
    SELECT pid FROM pg_stat_activity
    WHERE datname = current_database() AND CAST(? AS integer) = ANY(pg_blocking_pids(pid))
    UNION
    SELECT a.pid FROM pg_stat_activity a JOIN esperando e ON e.pid = ANY(pg_blocking_pids(a.pid))
    WHERE a.datname = current_database()
)
SELECT count(*) FROM pg_stat_activity a JOIN esperando e USING(pid)
WHERE a.application_name LIKE ?
SQL);
                $consulta->execute([$pid, $prefijo.'%']);
                $esperando = (int) $consulta->fetchColumn();
                $vivos = count(array_filter($procesos, fn (Process $p): bool => $p->isRunning()));
                if ($esperando === self::SOLICITUDES || $vivos < self::SOLICITUDES) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $limite);

            $db->rollBack();

            $resultados = [];
            foreach ($procesos as $proceso) {
                $this->assertSame(0, $proceso->wait(), 'Un proceso terminó con error: '.$proceso->getErrorOutput());
                $resultados[] = json_decode($proceso->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            foreach ($procesos as $proceso) {
                if ($proceso->isRunning()) {
                    $proceso->stop();
                }
            }
        }

        $this->assertSame(self::SOLICITUDES, $esperando, 'No se observaron las diez solicitudes esperando el bloqueo de la existencia.');

        $ganadoras = array_values(array_filter($resultados, fn (array $r): bool => $r['result'] === 'reserva'));
        $rechazadas = array_values(array_filter($resultados, fn (array $r): bool => $r['result'] === 'error'));
        $this->assertCount(1, $ganadoras);
        $this->assertCount(self::SOLICITUDES - 1, $rechazadas);
        foreach ($rechazadas as $rechazo) {
            $this->assertSame('ERR-M4-001', $rechazo['codigo']);
            $this->assertSame(409, $rechazo['http']);
            $this->assertSame([0, 0], $rechazo['events']);
        }
        $this->assertSame([1, 1], $ganadoras[0]['events']);
        $reserva = $ganadoras[0]['reserva'];
        $this->assertSame('activa', $reserva['estado_reserva']);
        $this->assertSame($existencia, $reserva['id_existencia']);
        $this->assertSame(1, $reserva['cantidad']);

        // Saldo final exactamente en 0, sin negativos ni sobreventa.
        $saldo = $db->prepare('SELECT cantidad_fisica, cantidad_reservada, cantidad_fisica - cantidad_reservada AS disponible FROM existencias WHERE id_existencia = ?');
        $saldo->execute([$existencia]);
        $this->assertSame([1.0, 1.0, 0.0], array_map('floatval', $saldo->fetch(PDO::FETCH_NUM)));

        $guardadas = $db->prepare('SELECT id_reserva, id_estado_reserva, cantidad, clave_idempotencia FROM reservas WHERE id_existencia = ?');
        $guardadas->execute([$existencia]);
        $filas = $guardadas->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $filas);
        $this->assertSame($reserva['id_reserva'], $filas[0]['id_reserva']);
        $this->assertSame(1, (int) $filas[0]['id_estado_reserva']);
        $this->assertContains($filas[0]['clave_idempotencia'], $claves);
    }
}
