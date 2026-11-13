<?php

declare(strict_types=1);

namespace Tests\Feature\Nucleo;

use App\Services\Nucleo\ContextoComercio;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContextoComercioWorkerTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
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

    public function test_dos_trabajos_del_mismo_worker_no_comparten_el_contexto_del_comercio(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        config(['queue.connections.regresion_contexto' => [
            'driver' => 'database', 'connection' => 'sqlite', 'table' => 'trabajos_cola',
            'queue' => 'regresion_contexto', 'retry_after' => 90, 'after_commit' => false,
        ]]);
        $observaciones = new ObservacionesContextoWorker;
        $this->app->instance(ObservacionesContextoWorker::class, $observaciones);
        /** @var Worker $worker */
        $worker = $this->app->make('queue.worker');
        $worker->setCache($this->app->make('cache')->store('array'));
        Queue::connection('regresion_contexto')->push(new ObservarContextoEnWorker(true));
        Queue::connection('regresion_contexto')->push(new ObservarContextoEnWorker(false));

        $resultado = $worker->daemon('regresion_contexto', 'regresion_contexto', new WorkerOptions(
            memory: 1024, timeout: 0, sleep: 0, stopWhenEmpty: true, maxJobs: 2, maxTime: 10,
        ));

        $this->assertSame(0, $resultado);
        $this->assertCount(2, $observaciones->inicios);
        $this->assertSame([true, false], array_column($observaciones->inicios, 'primero'));
        foreach ($observaciones->inicios as $inicio) {
            $this->assertSame($worker, $inicio['worker']);
            $this->assertSame($this->app, $inicio['aplicacion']);
            $this->assertSame(getmypid(), $inicio['pid']);
            $this->assertNull($inicio['comercio']);
            $this->assertFalse($inicio['sistema']);
        }
        $this->assertNotSame($observaciones->contextos[0], $observaciones->contextos[1]);
        $this->assertSame(4, $observaciones->contextos[0]->idActual());
        $this->assertTrue($observaciones->sistemaDurante);
        $this->assertFalse($observaciones->sistemaDespues);
        $this->assertDatabaseCount('trabajos_cola', 0);
        $this->assertDatabaseCount('trabajos_fallidos', 0);
    }
}

class ObservacionesContextoWorker
{
    /** @var list<array{primero: bool, worker: Worker, aplicacion: Application, pid: int|false, comercio: ?int, sistema: bool}> */
    public array $inicios = [];

    /** @var list<ContextoComercio> */
    public array $contextos = [];

    public bool $sistemaDurante = false;

    public bool $sistemaDespues = true;
}

class ObservarContextoEnWorker implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(public bool $primero) {}

    public function handle(ContextoComercio $contexto, ObservacionesContextoWorker $observaciones): void
    {
        $observaciones->contextos[] = $contexto;
        $observaciones->inicios[] = [
            'primero' => $this->primero, 'worker' => app('queue.worker'), 'aplicacion' => app(),
            'pid' => getmypid(), 'comercio' => $contexto->idActual(), 'sistema' => $contexto->esModoSistema(),
        ];

        if ($this->primero) {
            $contexto->establecer(4);
            $contexto->ejecutarComoSistema(function () use ($contexto, $observaciones): void {
                $observaciones->sistemaDurante = $contexto->esModoSistema();
            });
            $observaciones->sistemaDespues = $contexto->esModoSistema();
        }
        $this->delete();
    }
}
