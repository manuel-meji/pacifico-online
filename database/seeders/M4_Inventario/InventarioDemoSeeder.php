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
 * (id_sede = id_ubicacion). Los productos y variaciones coinciden con los de M3 en Supabase (DEF-M4-11): las 1 a 11 del catálogo
 * real y las 301, 401, 501, 502 y 601 de la demostración.
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
        // Catálogo real de M3, variaciones 1 a 11 (producto y comercio como en producto_variaciones).
        // Sede = id_sede de M2 (id_sede = id_ubicacion). El costo es cerca del 65 % del precio de venta.
        ['id_comercio' => 1, 'id_producto' => 1, 'id_variacion' => 1, 'id_ubicacion' => 1, 'cantidad' => 30, 'costo' => 6500.0, 'minimo' => 5],
        ['id_comercio' => 1, 'id_producto' => 2, 'id_variacion' => 2, 'id_ubicacion' => 1, 'cantidad' => 20, 'costo' => 18000.0, 'minimo' => 5],
        ['id_comercio' => 2, 'id_producto' => 3, 'id_variacion' => 3, 'id_ubicacion' => 3, 'cantidad' => 30, 'costo' => 10000.0, 'minimo' => 5],
        ['id_comercio' => 3, 'id_producto' => 4, 'id_variacion' => 4, 'id_ubicacion' => 4, 'cantidad' => 15, 'costo' => 21000.0, 'minimo' => 3],
        ['id_comercio' => 3, 'id_producto' => 5, 'id_variacion' => 5, 'id_ubicacion' => 4, 'cantidad' => 20, 'costo' => 16000.0, 'minimo' => 3],
        ['id_comercio' => 4, 'id_producto' => 6, 'id_variacion' => 6, 'id_ubicacion' => 5, 'cantidad' => 25, 'costo' => 25000.0, 'minimo' => 5],
        ['id_comercio' => 4, 'id_producto' => 6, 'id_variacion' => 7, 'id_ubicacion' => 5, 'cantidad' => 25, 'costo' => 25000.0, 'minimo' => 5],
        ['id_comercio' => 4, 'id_producto' => 7, 'id_variacion' => 8, 'id_ubicacion' => 5, 'cantidad' => 40, 'costo' => 12000.0, 'minimo' => 8],
        ['id_comercio' => 5, 'id_producto' => 8, 'id_variacion' => 9, 'id_ubicacion' => 6, 'cantidad' => 50, 'costo' => 4200.0, 'minimo' => 10],
        ['id_comercio' => 5, 'id_producto' => 9, 'id_variacion' => 10, 'id_ubicacion' => 6, 'cantidad' => 50, 'costo' => 2700.0, 'minimo' => 10],
        ['id_comercio' => 5, 'id_producto' => 10, 'id_variacion' => 11, 'id_ubicacion' => 6, 'cantidad' => 40, 'costo' => 2100.0, 'minimo' => 8],
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
