<?php

namespace Database\Seeders\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Enums\M4_Inventario\EfectoStock;
use App\Enums\M4_Inventario\TipoMovimiento;
use App\Models\M4_Inventario\Existencia;
use App\Services\M4_Inventario\DTO\DatosMovimiento;
use App\Services\M4_Inventario\KardexService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Existencias del escenario de demostración (aporte de M4 a la sección 2.2 del Comité).
 *
 *     php artisan db:seed --class="Database\Seeders\M4_Inventario\InventarioDemoSeeder"
 *
 * Orden de sembrado: primero M2 (comercios y sedes), después M3 (catálogo) y por último M4.
 *
 * Cada existencia entra con un movimiento de entrada y su línea de kardex, así el saldo
 * de la existencia coincide con el kardex. Es idempotente: si la existencia ya existe,
 * no se toca. Solo escribe tablas de M4.
 *
 * - Productos y variaciones: los del M3CatalogoSeeder (DEF-M4-11), variaciones 1 a 11
 *   del escenario y las 301, 401, 501, 502 y 601 de la demostración de M4.
 * - Sedes: no se escriben ids. Se piden a M2 por su contrato (obtenerSedesComercio) y se
 *   toma la sede habilitada por posición: SEDE_PRINCIPAL es la de menor id_sede («Sede
 *   principal») y SEGUNDA_SEDE la siguiente (en el comercio 1, «Bodega Liberia»). En una
 *   base nueva los id_sede dependen del orden de sembrado de M2.
 * - Variaciones 7 a 11: existencia y costo unitario de la tabla 5 del escenario 2.2.
 *   Las 1 a 6 no están en esa tabla: su costo es cerca del 65 % del precio de venta de M3.
 */
class InventarioDemoSeeder extends Seeder
{
    public const SEDE_PRINCIPAL = 0;

    public const SEGUNDA_SEDE = 1;

    /** Comercio, producto, variación, sede (posición), unidades, costo unitario y stock mínimo. */
    public const EXISTENCIAS = [
        // Demostración de M4, comercio 1 «Soluciones del Milenio»: transferencias y ajustes entre sus dos sedes.
        ['id_comercio' => 1, 'id_producto' => 301, 'id_variacion' => 501, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 40, 'costo' => 8500.0, 'minimo' => 10],
        ['id_comercio' => 1, 'id_producto' => 301, 'id_variacion' => 501, 'sede' => self::SEGUNDA_SEDE, 'cantidad' => 14, 'costo' => 8500.0, 'minimo' => 10],
        ['id_comercio' => 1, 'id_producto' => 301, 'id_variacion' => 502, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 6, 'costo' => 9000.0, 'minimo' => 8],
        // Comercio 2 «Temulandia»: otro comercio para mostrar el aislamiento (RN-12).
        ['id_comercio' => 2, 'id_producto' => 401, 'id_variacion' => 601, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 1, 'costo' => 21000.0, 'minimo' => 0],
        // Catálogo de M3, variaciones 1 a 6 (fuera de la tabla 5).
        ['id_comercio' => 1, 'id_producto' => 1, 'id_variacion' => 1, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 30, 'costo' => 6500.0, 'minimo' => 5],
        ['id_comercio' => 1, 'id_producto' => 2, 'id_variacion' => 2, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 20, 'costo' => 9750.0, 'minimo' => 5],
        ['id_comercio' => 2, 'id_producto' => 3, 'id_variacion' => 3, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 30, 'costo' => 2900.0, 'minimo' => 5],
        ['id_comercio' => 2, 'id_producto' => 4, 'id_variacion' => 4, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 15, 'costo' => 2300.0, 'minimo' => 3],
        ['id_comercio' => 3, 'id_producto' => 5, 'id_variacion' => 5, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 20, 'costo' => 16000.0, 'minimo' => 3],
        ['id_comercio' => 3, 'id_producto' => 6, 'id_variacion' => 6, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 20, 'costo' => 19500.0, 'minimo' => 3],
        // Tabla 5 del escenario 2.2: comercio 4 «Deportes Pacífico».
        ['id_comercio' => 4, 'id_producto' => 7, 'id_variacion' => 7, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 6, 'costo' => 4200.0, 'minimo' => 0],
        ['id_comercio' => 4, 'id_producto' => 7, 'id_variacion' => 8, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 2, 'costo' => 4600.0, 'minimo' => 0],
        ['id_comercio' => 4, 'id_producto' => 8, 'id_variacion' => 9, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 1, 'costo' => 3100.0, 'minimo' => 0],
        // Tabla 5 del escenario 2.2: comercio 5 «Café Volcán».
        ['id_comercio' => 5, 'id_producto' => 9, 'id_variacion' => 10, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 12, 'costo' => 2900.0, 'minimo' => 0],
        ['id_comercio' => 5, 'id_producto' => 10, 'id_variacion' => 11, 'sede' => self::SEDE_PRINCIPAL, 'cantidad' => 8, 'costo' => 1400.0, 'minimo' => 0],
    ];

    public function run(NucleoDependencia $nucleo, KardexService $kardex, ComerciosDependencia $comercios): void
    {
        $filas = $this->resolverSedes($comercios);

        $nucleo->ejecutarEnAmbito(fn () => DB::transaction(function () use ($kardex, $filas): void {
            foreach ($filas as $dato) {
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

    /**
     * Cambia la posición de la sede por su id_sede real, pedido a M2 una vez por comercio.
     *
     * @return list<array<string, int|float>>
     */
    private function resolverSedes(ComerciosDependencia $comercios): array
    {
        $sedesPorComercio = [];
        $filas = [];

        foreach (self::EXISTENCIAS as $dato) {
            $idComercio = $dato['id_comercio'];
            $sedesPorComercio[$idComercio] ??= $this->sedesHabilitadas($comercios, $idComercio);
            $idSede = $sedesPorComercio[$idComercio][$dato['sede']] ?? throw new RuntimeException(sprintf(
                'M2 no devolvió %s habilitada para el comercio %d. Siembre antes M2 (ComerciosDemoSeeder).',
                $dato['sede'] === self::SEDE_PRINCIPAL ? 'la sede principal' : 'una segunda sede', $idComercio,
            ));

            unset($dato['sede']);
            $filas[] = $dato + ['id_ubicacion' => $idSede];
        }

        return $filas;
    }

    /**
     * @return list<int> id_sede habilitados del comercio, de menor a mayor.
     */
    private function sedesHabilitadas(ComerciosDependencia $comercios, int $idComercio): array
    {
        $sedes = array_filter($comercios->obtenerSedesComercio($idComercio)['sedes'], fn (array $sede): bool => $sede['habilitada']);
        $ids = array_map(fn (array $sede): int => (int) $sede['id_sede'], $sedes);
        sort($ids);

        return $ids;
    }
}
