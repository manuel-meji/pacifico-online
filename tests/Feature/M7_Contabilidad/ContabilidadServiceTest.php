<?php

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use App\Services\M7_Contabilidad\ContabilidadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ContabilidadServiceTest extends TestCase
{
    public function test_conserva_el_retorno_publicado_y_advierte_que_no_genera_asientos(): void
    {
        Log::spy();
        $servicio = app(ContabilidadServiceInterface::class);
        $this->assertInstanceOf(ContabilidadService::class, $servicio);
        DB::connection()->enableQueryLog();

        $resultado = $servicio->registrarAsientoDespacho(100, 15000.00, 9000.00);

        $this->assertTrue($resultado);
        $this->assertSame([], DB::connection()->getQueryLog());
        Log::shouldHaveReceived('warning')->once()->with(
            'M7: registrarAsientoDespacho conserva el retorno anterior por compatibilidad temporal; no genera asientos. Su reemplazo requiere coordinación y versionado del contrato.',
            ['id_pedido' => 100],
        );
    }
}
