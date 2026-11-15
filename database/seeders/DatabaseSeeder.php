<?php

namespace Database\Seeders;

use Database\Seeders\M1_Portal\ClientesDemoSeeder;
use Database\Seeders\M1_Portal\PortalDemoSeeder;
use Database\Seeders\M2_Comercios\ComerciosDemoSeeder;
use Database\Seeders\M3_Catalogo\M3CatalogoSeeder;
use Database\Seeders\M4_Inventario\InventarioDemoSeeder;
use Database\Seeders\Nucleo\ContrasenaDemo;
use Database\Seeders\Nucleo\CuentasDemoSeeder;
use Illuminate\Database\Seeder;

/**
 * Carga la base del escenario de demostración (sección 2.2 del colectivo del PC4).
 * Para la ejecución oficial del recorrido la base se reinicia y se carga solo con esto:
 *     php artisan db:seed
 *
 * Requiere DEMO_CONTRASENA en el .env, con el largo mínimo del núcleo (ver ContrasenaDemo).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Antes de la primera escritura: sin una contraseña válida la carga quedaría a medias.
        ContrasenaDemo::exigir('DatabaseSeeder');

        // El orden importa: cada seeder lee, por contrato, lo que sembraron los anteriores.
        $this->call([
            ComerciosDemoSeeder::class,   // M2: comercios, sedes, comisiones y tarifas de envío.
            CuentasDemoSeeder::class,     // Núcleo: cuentas de los encargados (necesita los comercios).
            M3CatalogoSeeder::class,      // M3: categorías, productos y variaciones.
            PortalDemoSeeder::class,      // M1: menú de categorías y destacados (lee M2 y M3).
            ClientesDemoSeeder::class,    // M1: cliente del escenario 1 (la cuenta la crea el núcleo).
            InventarioDemoSeeder::class,  // M4: existencias (lee las sedes de M2 y el catálogo de M3).
            M7_PlanCuentasSeeder::class,  // M7: plan de cuentas de cada comercio.
        ]);

        // M5PedidosSeeder no se llama: en el escenario el pedido se crea en vivo durante el
        // recorrido (carrito, reserva, pago y despacho), así que la base arranca sin pedidos.
    }
}
