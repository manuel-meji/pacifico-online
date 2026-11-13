<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Events\M6_Pagos\PagoConfirmado;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait PreparaIntegracionM6
{
    private function prepararIntegracionM6(): void
    {
        if (! Schema::hasTable('eventos_procesados')) {
            (require base_path('database/migrations/M7_Contabilidad/2026_10_20_191733_create_eventos_procesados_table.php'))->up();
        }
        foreach ([
            '2026_11_13_061033_crear_registros_financieros_m6.php',
            '2026_11_13_203419_crear_libro_plataforma_m7.php',
            '2026_11_13_203419_preparar_separacion_eventos_m6.php',
        ] as $archivo) {
            (require base_path('database/migrations/M7_Contabilidad/'.$archivo))->up();
        }
    }

    private function registrarCobroGlobal(int $idPago, int $idPedido = 10, float $monto = 100): void
    {
        event(new PagoConfirmado((string) Str::uuid(), $idPedido, $idPago, $monto, '2026-11-10T12:00:00Z'));
    }
}
