<?php

declare(strict_types=1);

namespace Tests\Feature\M3_Catalogo\Eventos;

use App\Events\M2_Comercios\ComercioAprobado;
use App\Events\M2_Comercios\ComercioReactivado;
use App\Events\M2_Comercios\ComercioSuspendido;
use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Events\M4_Inventario\ExistenciaRepuesta;
use App\Models\M3_Catalogo\Producto;
use App\Models\M3_Catalogo\ProductoVariacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Unit\M3_Catalogo\Concerns\PreparaEsquemaM3;

/** Eventos que M3 consume: ExistenciaAgotada/Repuesta (M4) y ComercioAprobado/Reactivado/Suspendido (M2). */
class M3_EventosConsumidosTest extends TestCase
{
    use PreparaEsquemaM3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaM3();

        DB::statement('DROP TABLE IF EXISTS estado_comercios_catalogo');
        DB::statement('CREATE TABLE estado_comercios_catalogo (
            id_comercio INTEGER PRIMARY KEY,
            activo BOOLEAN NOT NULL DEFAULT 0,
            fecha_ultimo_evento DATETIME NULL,
            id_ultimo_evento VARCHAR(36) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
    }

    private function variacion(int $idComercio = 1, bool $disponible = true): ProductoVariacion
    {
        return $this->comoSistema(function () use ($idComercio, $disponible) {
            $producto = Producto::create([
                'id_comercio' => $idComercio,
                'id_categoria' => 1,
                'nombre' => 'Producto de prueba',
                'estado_producto' => 'publicado',
            ]);

            return ProductoVariacion::create([
                'id_producto' => $producto->id_producto,
                'id_comercio' => $idComercio,
                'sku' => 'SKU-'.Str::random(8),
                'precio' => 10000,
                'tarifa_impuesto' => 13,
                'disponible' => $disponible,
            ]);
        });
    }

    private function disponible(ProductoVariacion $variacion): bool
    {
        return (bool) DB::table('producto_variaciones')
            ->where('id_variacion', $variacion->id_variacion)
            ->value('disponible');
    }

    private function activo(int $idComercio): ?bool
    {
        $valor = DB::table('estado_comercios_catalogo')->where('id_comercio', $idComercio)->value('activo');

        return $valor === null ? null : (bool) $valor;
    }

    // ---------------------------------------------------------------- M4 -> M3

    public function test_existencia_agotada_marca_la_variacion_como_no_disponible(): void
    {
        $v = $this->variacion(1, true);

        event(new ExistenciaAgotada(1, (int) $v->id_producto, (int) $v->id_variacion));

        $this->assertFalse($this->disponible($v));
    }

    public function test_existencia_repuesta_marca_la_variacion_como_disponible(): void
    {
        $v = $this->variacion(1, false);

        event(new ExistenciaRepuesta(1, (int) $v->id_producto, (int) $v->id_variacion, 5));

        $this->assertTrue($this->disponible($v));
    }

    public function test_existencia_agotada_repetida_es_idempotente(): void
    {
        $v = $this->variacion(1, true);
        $evento = new ExistenciaAgotada(1, (int) $v->id_producto, (int) $v->id_variacion);

        event($evento);
        event($evento);

        $this->assertFalse($this->disponible($v));
    }

    public function test_evento_de_otro_comercio_se_ignora_rn12(): void
    {
        $v = $this->variacion(1, true);

        event(new ExistenciaAgotada(2, (int) $v->id_producto, (int) $v->id_variacion));

        $this->assertTrue($this->disponible($v));
    }

    public function test_evento_de_variacion_inexistente_no_lanza_error(): void
    {
        event(new ExistenciaAgotada(1, 999, 999));

        $this->assertSame(0, DB::table('producto_variaciones')->count());
    }

    // ---------------------------------------------------------------- M2 -> M3

    private function t(string $hora): Carbon
    {
        return Carbon::parse("2026-11-10 {$hora}");
    }

    private function aprobado(int $id, string $hora, ?string $idEvento = null): ComercioAprobado
    {
        return new ComercioAprobado($idEvento ?? (string) Str::uuid(), $this->t($hora), $id, 'activo', 'Comercio '.$id);
    }

    private function suspendido(int $id, string $hora, ?string $idEvento = null): ComercioSuspendido
    {
        return new ComercioSuspendido($idEvento ?? (string) Str::uuid(), $this->t($hora), $id, 'suspendido', 'Motivo de prueba');
    }

    private function reactivado(int $id, string $hora, ?string $idEvento = null): ComercioReactivado
    {
        return new ComercioReactivado($idEvento ?? (string) Str::uuid(), $this->t($hora), $id, 'activo', 'Comercio '.$id);
    }

    public function test_comercio_aprobado_crea_el_estado_activo(): void
    {
        event($this->aprobado(7, '10:00:00'));

        $this->assertTrue($this->activo(7));
    }

    public function test_comercio_suspendido_y_reactivado_cambian_el_estado(): void
    {
        event($this->aprobado(7, '10:00:00'));
        event($this->suspendido(7, '11:00:00'));
        $this->assertFalse($this->activo(7));

        event($this->reactivado(7, '12:00:00'));
        $this->assertTrue($this->activo(7));
    }

    public function test_evento_de_comercio_repetido_se_descarta(): void
    {
        $id = (string) Str::uuid();
        event($this->suspendido(7, '10:00:00', $id));
        // Mismo id_evento con otra accion: debe descartarse por duplicado.
        event($this->reactivado(7, '11:00:00', $id));

        $this->assertFalse($this->activo(7));
    }

    public function test_evento_anterior_no_sobrescribe_a_uno_mas_reciente(): void
    {
        event($this->suspendido(7, '12:00:00'));
        // Llega tarde un ComercioAprobado generado antes de la suspension.
        event($this->aprobado(7, '09:00:00'));

        $this->assertFalse($this->activo(7));
    }

    // ------------------------------------------------- tolerancia a otros modulos

    public function test_evento_de_comercio_con_forma_minima_es_tolerado(): void
    {
        // Otros modulos publican el evento con objetos simples (no la clase real).
        $payload = (object) [
            'id_evento' => (string) Str::uuid(),
            'fecha_hora' => '2026-11-10 10:00:00',
            'id_comercio' => 9,
        ];

        event(ComercioAprobado::class, [$payload]);

        $this->assertTrue($this->activo(9));
    }

    public function test_evento_sin_id_comercio_se_ignora_sin_error(): void
    {
        event(ComercioAprobado::class, [(object) ['id_evento' => (string) Str::uuid()]]);

        $this->assertNull($this->activo(1));
    }

    public function test_un_fallo_interno_no_interrumpe_al_publicador(): void
    {
        DB::statement('DROP TABLE estado_comercios_catalogo');
        Log::spy();

        event($this->aprobado(7, '10:00:00'));

        Log::shouldHaveReceived('error')->once();
    }
}
