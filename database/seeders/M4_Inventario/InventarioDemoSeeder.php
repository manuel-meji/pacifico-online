<?php

namespace Database\Seeders\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Enums\M4_Inventario\EfectoStock;
use App\Enums\M4_Inventario\TipoMovimiento;
use App\Models\M4_Inventario\Existencia;
use App\Services\M4_Inventario\DTO\DatosMovimiento;
use App\Services\M4_Inventario\KardexService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Existencias del escenario de demostración (aporte de M4 a la sección 2.2 del Comité).
 *
 *     php artisan db:seed --class="Database\Seeders\M4_Inventario\InventarioDemoSeeder"
 *
 * Cada existencia entra con un movimiento de entrada y su línea de kardex, así el saldo
 * de la existencia coincide con el kardex. Es idempotente: si la existencia ya existe,
 * no se toca. Solo escribe tablas de M4. Comercios y sedes son los reales de M2 en Supabase
 * (id_sede = id_ubicacion). Los productos y variaciones deben coincidir con los de M3 (DEF-M4-11).
 */
class InventarioDemoSeeder extends Seeder
{
    /** Comercio, producto, variación, sede, unidades, costo unitario y stock mínimo. */
    public const EXISTENCIAS = [
        // Comercio 1 «Soluciones del Milenio»: sede 1 «Sede principal» y sede 2 «Bodega Liberia» (M2, PR #151).
        ['id_comercio' => 1, 'id_producto' => 301, 'id_variacion' => 501, 'id_ubicacion' => 1, 'cantidad' => 40, 'costo' => 8500.0, 'minimo' => 10],
        ['id_comercio' => 1, 'id_producto' => 301, 'id_variacion' => 501, 'id_ubicacion' => 2, 'cantidad' => 14, 'costo' => 8500.0, 'minimo' => 10],
        ['id_comercio' => 1, 'id_producto' => 301, 'id_variacion' => 502, 'id_ubicacion' => 1, 'cantidad' => 6, 'costo' => 9000.0, 'minimo' => 8],
        // Comercio 2 «Temulandia», sede 3: otro comercio para mostrar el aislamiento (RN-12).
        ['id_comercio' => 2, 'id_producto' => 401, 'id_variacion' => 601, 'id_ubicacion' => 3, 'cantidad' => 1, 'costo' => 21000.0, 'minimo' => 0],
    ];

    public function run(NucleoDependencia $nucleo, KardexService $kardex): void
    {
        $nucleo->ejecutarEnAmbito(fn () => DB::transaction(function () use ($kardex): void {
            foreach (self::EXISTENCIAS as $dato) {
                $clave = array_intersect_key($dato, ['id_comercio' => 0, 'id_variacion' => 0, 'id_ubicacion' => 0]);

                if (Existencia::query()->where($clave)->exists()) {
                    continue;
                }

                $existencia = Existencia::query()->create($clave + [
                    'id_producto' => $dato['id_producto'],
                    'cantidad_fisica' => $dato['cantidad'],
                    'costo_promedio' => $dato['costo'],
                    'stock_minimo' => $dato['minimo'],
                ]);

                $kardex->registrar($existencia, new DatosMovimiento(
                    TipoMovimiento::Entrada, EfectoStock::Aumenta, $dato['cantidad'], $dato['costo'], 0,
                    'Inventario inicial del escenario de demostración',
                ));
            }
        }), publica: true);
    }
}
