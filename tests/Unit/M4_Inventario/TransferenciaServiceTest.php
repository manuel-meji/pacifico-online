<?php

namespace Tests\Unit\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\VerificadorAutorizacionesInterface;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\M4_Inventario\EsquemaInventarioPrueba;
use Tests\TestCase;

/**
 * Reglas de la transferencia entre ubicaciones (CU-M4-05, RF-M4-005, RN-M4-012) en el servicio.
 */
class TransferenciaServiceTest extends TestCase
{
    use EsquemaInventarioPrueba;

    protected function setUp(): void
    {
        parent::setUp();
        config(['integraciones.m4.nucleo.modo' => 'real']);
        $this->crearEsquemaNucleo();
        $this->crearEsquemaInventario();
        $this->existencia(['id_variacion' => 10, 'id_ubicacion' => 1, 'cantidad_fisica' => 10, 'cantidad_reservada' => 3, 'costo_promedio' => 5000]);
        $this->autorizar('autorizacion-traslado-5', 5);
        $this->autorizar('autorizacion-traslado-8', 8);
    }

    private function autorizar(string $id, int $cantidad, array $ubicaciones = [1, 2]): void
    {
        config(["integraciones.m4.autorizaciones.registradas.{$id}" => [
            'operacion' => VerificadorAutorizacionesInterface::OPERACION_TRANSFERENCIA, 'id_comercio' => 1, 'id_variacion' => 10,
            'ubicaciones' => $ubicaciones, 'cantidad' => $cantidad, 'id_autorizador' => 2,
            'fecha_autorizacion' => '2026-11-09T09:00:00-06:00', 'vence' => '2099-12-31T23:59:59-06:00',
        ]]);
    }

    /** @return array<string, mixed> */
    private function transferir(int $cantidad, ?string $autorizacion, int $origen = 1, int $destino = 2): array
    {
        return app(InventarioModuleInterface::class)->registrarTransferencia([
            'id_comercio' => 1, 'id_variacion' => 10, 'id_ubicacion_origen' => $origen, 'id_ubicacion_destino' => $destino,
            'cantidad' => $cantidad, 'justificacion' => 'Reposición de la bodega', 'id_autorizacion' => $autorizacion,
        ]);
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

    private function fisico(int $ubicacion): ?int
    {
        $valor = DB::table('existencias')->where('id_variacion', 10)->where('id_ubicacion', $ubicacion)->value('cantidad_fisica');

        return $valor === null ? null : (int) $valor;
    }

    public function test_la_transferencia_mueve_unidades_sin_cambiar_el_total_y_deja_dos_lineas_con_la_misma_referencia(): void
    {
        $this->existencia(['id_variacion' => 10, 'id_ubicacion' => 2, 'cantidad_fisica' => 4, 'costo_promedio' => 6000]);

        $respuesta = $this->transferir(5, 'autorizacion-traslado-5');

        $this->assertSame(5, $this->fisico(1));
        $this->assertSame(9, $this->fisico(2));
        $this->assertSame(14, $this->fisico(1) + $this->fisico(2), 'La suma de origen y destino no cambia.');

        [$salida, $entrada] = $respuesta['movimientos'];
        $this->assertSame(['transferencia_salida', 'transferencia_entrada'], [$salida['tipo_movimiento'], $entrada['tipo_movimiento']]);
        $this->assertSame($respuesta['id_transferencia'], $salida['referencia_id']);
        $this->assertSame($respuesta['id_transferencia'], $entrada['referencia_id']);
        $this->assertSame([2, 2], [$salida['id_autorizador'], $entrada['id_autorizador']]);
        $this->assertSame('transferencia', $salida['referencia_tipo']);
        $this->assertSame(2, DB::table('kardex_inventario')->count());
        $this->assertSame(2, DB::table('movimientos_inventario')->where('referencia', $respuesta['id_transferencia'])->count());

        // Costo promedio del destino: (4 × 6000 + 5 × 5000) / 9.
        $costoDestino = (float) DB::table('existencias')->where('id_ubicacion', 2)->value('costo_promedio');
        $this->assertEqualsWithDelta(5444.44, $costoDestino, 0.01);
    }

    public function test_si_el_destino_no_tiene_existencia_se_crea_con_el_costo_del_origen(): void
    {
        $this->transferir(5, 'autorizacion-traslado-5');

        $this->assertSame(5, $this->fisico(2));
        $this->assertEqualsWithDelta(5000, (float) DB::table('existencias')->where('id_ubicacion', 2)->value('costo_promedio'), 0.01);
    }

    public function test_sin_autorizacion_valida_no_se_transfiere_y_no_se_puede_reutilizar(): void
    {
        $this->assertSame('ERR-005', $this->codigoDe(fn () => $this->transferir(5, null)));
        $this->assertSame('ERR-002', $this->codigoDe(fn () => $this->transferir(5, 'autorizacion-inexistente')));
        $this->assertSame('ERR-002', $this->codigoDe(fn () => $this->transferir(4, 'autorizacion-traslado-5')), 'La cantidad debe coincidir.');
        $this->assertSame(10, $this->fisico(1));
        $this->assertNull($this->fisico(2), 'El rollback deshace la existencia de destino creada.');

        $this->transferir(5, 'autorizacion-traslado-5');
        $this->assertSame('ERR-002', $this->codigoDe(fn () => $this->transferir(5, 'autorizacion-traslado-5')), 'La autorización es de un solo uso.');
        $this->assertSame(5, $this->fisico(1));
    }

    public function test_no_se_trasladan_unidades_reservadas(): void
    {
        // Físico 10, reservado 3: solo 7 disponibles.
        $this->assertSame('ERR-M4-001', $this->codigoDe(fn () => $this->transferir(8, 'autorizacion-traslado-8')));
        $this->assertSame(10, $this->fisico(1));
        $this->assertSame(0, DB::table('movimientos_inventario')->count());
    }

    public function test_origen_igual_a_destino_es_error_de_validacion(): void
    {
        $this->autorizar('autorizacion-misma-sede', 1, [1, 1]);

        $this->assertSame('ERR-005', $this->codigoDe(fn () => $this->transferir(1, 'autorizacion-misma-sede', 1, 1)));
    }

    public function test_un_comercio_suspendido_no_transfiere(): void
    {
        DB::table('estados_comercio_inventario')->insert([
            'id_comercio' => 1, 'bloqueado' => true, 'motivo' => 'Suspendido', 'fecha_evento' => Carbon::now(), 'id_evento' => 'evt-prueba',
        ]);

        $this->assertSame('ERR-M4-015', $this->codigoDe(fn () => $this->transferir(5, 'autorizacion-traslado-5')));
        $this->assertSame(10, $this->fisico(1));
    }
}
