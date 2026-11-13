<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use PDO;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('m7-concurrencia')]
class ReembolsosConcurrentesTest extends TestCase
{
    public function test_dos_reembolsos_simultaneos_no_superan_el_saldo(): void
    {
        if (getenv('M7_TEST_PG_ENABLE') !== '1') {
            $this->markTestSkipped('Requiere PostgreSQL local dedicado y M7_TEST_PG_ENABLE=1; nunca usa Supabase.');
        }

        $host = getenv('M7_TEST_PG_HOST');
        $base = getenv('M7_TEST_PG_DATABASE');
        $usuario = getenv('M7_TEST_PG_USER');
        $puerto = getenv('M7_TEST_PG_PORT') ?: '5432';
        $clave = getenv('M7_TEST_PG_PASSWORD') ?: '';
        if ($host !== '127.0.0.1' || $base !== 'pacifico_m7_test' || ! $usuario || ! ctype_digit($puerto)) {
            $this->fail('La prueba exige host 127.0.0.1, base dedicada pacifico_m7_test y usuario explícito.');
        }

        $conexion = [
            'driver' => 'pgsql', 'host' => $host, 'port' => $puerto, 'database' => $base,
            'username' => $usuario, 'password' => $clave, 'charset' => 'utf8',
            'prefix' => '', 'sslmode' => 'prefer',
        ];
        $db = new PDO("pgsql:host={$host};port={$puerto};dbname={$base}", $usuario, $clave, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $esquema = 'm7_prueba_'.bin2hex(random_bytes(8));
        $db->exec('CREATE SCHEMA '.$esquema);
        $procesos = [];

        try {
            $db->exec('SET search_path TO '.$esquema);
            $this->crearTablas($db);
            $conexion['search_path'] = $esquema;
            $prefijo = $esquema.'_';
            $db->beginTransaction();
            $db->query("SELECT id_cuenta_contable FROM cuentas_contables WHERE codigo = '1.1.01' FOR UPDATE");
            $php = (new PhpExecutableFinder)->find(false) ?: PHP_BINARY;

            foreach ([21, 22] as $numero) {
                $proceso = new Process(
                    [$php, __DIR__.DIRECTORY_SEPARATOR.'reembolso_concurrente_worker.php', base_path()],
                    base_path(),
                    ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => ''],
                    json_encode(['conexion' => $conexion, 'sesion' => $prefijo.$numero, 'numero' => $numero], JSON_THROW_ON_ERROR),
                    60,
                );
                $proceso->start();
                $procesos[] = $proceso;
            }

            $limite = microtime(true) + 30;
            do {
                $db->query('SELECT pg_stat_clear_snapshot()');
                $consulta = $db->prepare("SELECT count(*) FROM pg_stat_activity WHERE application_name IN (?, ?) AND wait_event_type = 'Lock'");
                $consulta->execute([$prefijo.'21', $prefijo.'22']);
                $esperando = (int) $consulta->fetchColumn();
                if ($esperando < 2) {
                    usleep(20000);
                }
            } while ($esperando < 2 && microtime(true) < $limite);

            $db->rollBack();
            $this->assertSame(2, $esperando, 'Ambas sesiones deben esperar el mismo bloqueo.');
            $resultados = [];
            foreach ($procesos as $proceso) {
                $this->assertSame(0, $proceso->wait(), $proceso->getErrorOutput());
                $resultados[] = json_decode($proceso->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }

            $this->assertCount(1, array_filter($resultados, fn (array $resultado): bool => $resultado['estado'] === 'registrado'));
            $rechazados = array_values(array_filter($resultados, fn (array $resultado): bool => $resultado['estado'] === 'error'));
            $this->assertCount(1, $rechazados);
            $this->assertStringContainsString('excede el saldo', $rechazados[0]['mensaje']);
            $this->assertSame(1, (int) $db->query("SELECT count(*) FROM registros_financieros_m6 WHERE tipo = 'REEMBOLSO'")->fetchColumn());
            $this->assertSame(7000, (int) $db->query("SELECT SUM(subtotal_centimos + impuesto_centimos + envio_centimos) FROM registros_financieros_m6 WHERE tipo = 'REEMBOLSO'")->fetchColumn());
            $this->assertSame(1, (int) $db->query("SELECT count(*) FROM asientos_contables WHERE tipo = 'REEMBOLSO'")->fetchColumn());
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            foreach ($procesos as $proceso) {
                if ($proceso->isRunning()) {
                    $proceso->stop(2);
                }
            }
            $db->exec('DROP SCHEMA '.$esquema.' CASCADE');
        }
    }

    private function crearTablas(PDO $db): void
    {
        $db->exec(<<<'SQL'
CREATE TABLE cuentas_contables (
    id_cuenta_contable bigserial PRIMARY KEY, id_comercio bigint NOT NULL, codigo varchar(30) NOT NULL, activa boolean NOT NULL
);
CREATE TABLE eventos_procesados (
    id_evento_procesado bigserial PRIMARY KEY, evento_id uuid NOT NULL UNIQUE, tipo_evento varchar(80) NOT NULL,
    estado varchar(20) NOT NULL, fecha_recepcion timestamp NOT NULL, fecha_procesamiento timestamp,
    ultimo_error text, created_at timestamp, updated_at timestamp
);
CREATE TABLE asientos_contables (
    id_asiento bigserial PRIMARY KEY, id_comercio bigint NOT NULL, id_pedido bigint, id_subpedido bigint,
    id_pago bigint, id_asiento_original bigint, evento_id uuid NOT NULL, tipo_evento varchar(80) NOT NULL,
    tipo varchar(40) NOT NULL, fecha timestamp NOT NULL, estado varchar(20) NOT NULL, created_at timestamp, updated_at timestamp
);
CREATE TABLE detalle_asientos (
    id_detalle_asiento bigserial PRIMARY KEY, id_asiento bigint NOT NULL, id_cuenta_contable bigint NOT NULL,
    numero_linea integer NOT NULL, debe numeric(15,2) NOT NULL, haber numeric(15,2) NOT NULL, created_at timestamp, updated_at timestamp
);
CREATE TABLE registros_financieros_m6 (
    id_registro bigserial PRIMARY KEY, referencia varchar(120) NOT NULL UNIQUE, tipo varchar(20) NOT NULL,
    id_operacion bigint NOT NULL, id_pago bigint NOT NULL, id_pedido bigint NOT NULL, id_subpedido bigint NOT NULL,
    id_comercio bigint NOT NULL, id_asiento bigint NOT NULL UNIQUE REFERENCES asientos_contables (id_asiento),
    subtotal_centimos bigint NOT NULL, impuesto_centimos bigint NOT NULL, envio_centimos bigint NOT NULL,
    huella varchar(64) NOT NULL, created_at timestamp, updated_at timestamp, UNIQUE (tipo, id_operacion)
);
INSERT INTO cuentas_contables (id_comercio, codigo, activa) VALUES
    (11, '1.1.01', true), (11, '2.1.02', true), (11, '4.1.01', true), (11, '4.1.02', true);
INSERT INTO asientos_contables (id_comercio, id_pedido, id_subpedido, id_pago, evento_id, tipo_evento, tipo, fecha, estado)
    VALUES (11, 8942, 1001, 7, '11111111-1111-4111-8111-111111111111', 'FacturaGenerada', 'COBRO', '2026-11-11', 'REGISTRADO');
INSERT INTO registros_financieros_m6 (referencia, tipo, id_operacion, id_pago, id_pedido, id_subpedido, id_comercio, id_asiento, subtotal_centimos, impuesto_centimos, envio_centimos, huella)
    VALUES ('factura:7:1001', 'FACTURA', 31, 7, 8942, 1001, 11, 1, 10000, 0, 0, 'original');
SQL);
    }
}
