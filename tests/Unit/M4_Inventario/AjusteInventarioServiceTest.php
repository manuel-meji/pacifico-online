<?php

namespace Tests\Unit\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\VerificadorAutorizacionesInterface;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Closure;
use Illuminate\Support\Facades\DB;
use Tests\Feature\M4_Inventario\EsquemaInventarioPrueba;
use Tests\TestCase;

/**
 * Reglas del conteo físico y ajuste (CU-M4-02) en el servicio.
 * Casos de prueba CP-M4-15 a CP-M4-17 (sección 5 del PC4).
 */
class AjusteInventarioServiceTest extends TestCase
{
    use EsquemaInventarioPrueba;

    protected function setUp(): void
    {
        parent::setUp();
        config(['integraciones.m4.nucleo.modo' => 'real']);
        $this->crearEsquemaNucleo();
        $this->crearEsquemaInventario();
        $this->existencia(['id_variacion' => 10, 'id_ubicacion' => 1, 'cantidad_fisica' => 10, 'cantidad_reservada' => 3, 'costo_promedio' => 5000]);
        config(['integraciones.m4.autorizaciones.registradas.autorizacion-merma' => [
            'operacion' => VerificadorAutorizacionesInterface::OPERACION_AJUSTE, 'id_comercio' => 1, 'id_variacion' => 10,
            'ubicaciones' => [1], 'cantidad' => 2, 'id_autorizador' => 2,
            'fecha_autorizacion' => '2026-11-09T09:00:00-06:00', 'vence' => '2099-12-31T23:59:59-06:00',
        ]]);
    }

    /** @return array<string, mixed> */
    private function ajustar(int $vista, int $contada, ?string $autorizacion = null, string $motivo = 'merma'): array
    {
        return app(InventarioModuleInterface::class)->registrarAjusteInventario(array_filter([
            'id_comercio' => 1, 'id_variacion' => 10, 'id_ubicacion' => 1,
            'cantidad_sistema_vista' => $vista, 'cantidad_contada' => $contada,
            'motivo_ajuste' => $motivo, 'justificacion' => 'Conteo mensual', 'id_autorizacion' => $autorizacion,
        ], fn ($valor): bool => $valor !== null));
    }

    private function codigoDe(Closure $operacion): string
    {
        try {
            $operacion();
        } catch (ErrorInventarioException $e) {
            return $e->codigo;
        }

        $this->fail('Se esperaba un error del contrato.');
    }

    private function fisico(): int
    {
        return (int) DB::table('existencias')->where('id_variacion', 10)->value('cantidad_fisica');
    }

    /** CP-M4-15 · RN-M4-011: sin diferencia se registra el conteo y no se genera movimiento ni kardex. */
    public function test_un_conteo_sin_diferencia_no_genera_movimiento(): void
    {
        $ajuste = $this->ajustar(10, 10);

        $this->assertSame(0, $ajuste['diferencia']);
        $this->assertNull($ajuste['id_movimiento']);
        $this->assertSame(0, DB::table('movimientos_inventario')->count());
        $this->assertSame(10, $this->fisico());
    }

    /** CP-M4-16 · RN-M4-011, RN-M4-001: el ajuste con diferencia exige motivo válido, autorización y no baja de lo comprometido. */
    public function test_el_ajuste_con_diferencia_exige_motivo_autorizacion_y_respeta_lo_comprometido(): void
    {
        $this->assertSame('ERR-005', $this->codigoDe(fn () => $this->ajustar(10, 8)));
        $this->assertSame('ERR-005', $this->codigoDe(fn () => $this->ajustar(10, 8, 'autorizacion-merma', 'perdida')));
        $this->assertSame('ERR-M4-017', $this->codigoDe(fn () => $this->ajustar(10, 2, 'autorizacion-merma')));
        $this->assertSame('ERR-M4-016', $this->codigoDe(fn () => $this->ajustar(9, 8, 'autorizacion-merma')));
        $this->assertSame(10, $this->fisico(), 'Ningún intento rechazado cambia la existencia.');
        $this->assertSame(0, DB::table('movimientos_inventario')->count());
    }

    /** CP-M4-17 · RN-M4-011, RN-M4-003: con autorización se ajusta el físico, queda en el kardex y la autorización no se reutiliza. */
    public function test_el_ajuste_autorizado_se_aplica_una_vez_y_queda_en_el_kardex(): void
    {
        $ajuste = $this->ajustar(10, 8, 'autorizacion-merma');

        $this->assertSame(-2, $ajuste['diferencia']);
        $this->assertNotNull($ajuste['id_movimiento']);
        $this->assertSame(8, $this->fisico());
        $this->assertSame('ajuste', DB::table('movimientos_inventario')->value('tipo'));
        $this->assertSame(1, DB::table('kardex_inventario')->count());
        $this->assertSame('ERR-002', $this->codigoDe(fn () => $this->ajustar(8, 6, 'autorizacion-merma')), 'La autorización es de un solo uso.');
    }
}
