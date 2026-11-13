<?php

declare(strict_types=1);

namespace Tests\Feature\M3_Catalogo\Seeder;

use Database\Seeders\M3_Catalogo\M3CatalogoSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;
use Tests\Unit\M3_Catalogo\Concerns\PreparaDatosConsultasM3;

/**
 * Freno de seguridad de M3CatalogoSeeder: no cambia el significado de una variación que otro
 * módulo ya referencia, salvo con M3_SEMBRAR_FORZAR=1.
 *
 * Las tablas de M1, M4 y M5 se crean aquí con las columnas mínimas; no se usan sus modelos.
 */
class M3_FrenoSeederTest extends TestCase
{
    use PreparaDatosConsultasM3;

    private const VARIABLE = 'M3_SEMBRAR_FORZAR';

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaConsultasM3();
        $this->forzar(null);
    }

    protected function tearDown(): void
    {
        $this->forzar(null);
        parent::tearDown();
    }

    private function forzar(?string $valor): void
    {
        if ($valor === null) {
            unset($_ENV[self::VARIABLE], $_SERVER[self::VARIABLE]);
            putenv(self::VARIABLE);

            return;
        }

        $_ENV[self::VARIABLE] = $valor;
        $_SERVER[self::VARIABLE] = $valor;
        putenv(self::VARIABLE.'='.$valor);
    }

    private function sembrar(): void
    {
        $this->seed(M3CatalogoSeeder::class);
    }

    /**
     * Catálogo anterior reducido: la variación 1 (mouse) no cambia con el seeder nuevo; la 9 era
     * café en grano del comercio 5 y pasa a ser la botella del comercio 4.
     */
    private function sembrarCatalogoAnterior(): void
    {
        foreach ([1 => 1, 8 => 5] as $idProducto => $idComercio) {
            DB::table('productos')->insert([
                'id_producto' => $idProducto, 'id_comercio' => $idComercio, 'id_categoria' => 1,
                'nombre' => "Producto anterior {$idProducto}", 'estado_producto' => 'publicado',
            ]);
        }

        DB::table('producto_variaciones')->insert([
            ['id_variacion' => 1, 'id_producto' => 1, 'id_comercio' => 1, 'sku' => 'SDM-LOG-MQ291-NEG', 'precio' => 10000, 'tarifa_impuesto' => 13],
            ['id_variacion' => 9, 'id_producto' => 8, 'id_comercio' => 5, 'sku' => 'CV-GRA-500G-MED', 'precio' => 6500, 'tarifa_impuesto' => 1],
        ]);
    }

    /** Tabla mínima de otro módulo con las columnas que el freno revisa. */
    private function tablaAjena(string $tabla, string ...$columnas): void
    {
        Schema::dropIfExists($tabla);
        Schema::create($tabla, function (Blueprint $t) use ($columnas): void {
            $t->id();
            foreach ($columnas as $columna) {
                $t->unsignedBigInteger($columna)->nullable();
            }
        });
    }

    private function skuDeLaVariacion(int $id): ?string
    {
        return DB::table('producto_variaciones')->where('id_variacion', $id)->value('sku');
    }

    public function test_en_una_base_vacia_siembra_aunque_otros_modulos_tengan_filas(): void
    {
        $this->tablaAjena('pedidos_lineas', 'id_variacion');
        DB::table('pedidos_lineas')->insert(['id_variacion' => 9]);

        $this->sembrar();

        $this->assertSame('DP-BOT-TER-NEG', $this->skuDeLaVariacion(9));
    }

    public function test_se_detiene_si_otro_modulo_referencia_una_variacion_que_cambia_y_no_escribe_nada(): void
    {
        $this->sembrarCatalogoAnterior();
        $this->tablaAjena('pedidos_lineas', 'id_variacion');
        DB::table('pedidos_lineas')->insert([['id_variacion' => 9], ['id_variacion' => 9]]);

        try {
            $this->sembrar();
            $this->fail('Debió detenerse: M5 referencia la variación 9, que cambia.');
        } catch (RuntimeException $error) {
            $mensaje = $error->getMessage();

            $this->assertStringContainsString('no se sembró nada', $mensaje);
            $this->assertStringContainsString('cambia el significado de las variaciones 9', $mensaje);
            $this->assertStringContainsString('M5: pedidos_lineas.id_variacion: 2 filas (ids 9)', $mensaje);
            $this->assertStringContainsString('M3_SEMBRAR_FORZAR=1', $mensaje);
        }

        $this->assertSame('CV-GRA-500G-MED', $this->skuDeLaVariacion(9));
        $this->assertSame(5, (int) DB::table('productos')->where('id_producto', 8)->value('id_comercio'));
        $this->assertSame(2, DB::table('productos')->count());
        $this->assertSame(0, DB::table('categorias')->count());
        $this->assertSame(0, DB::table('estado_comercios_catalogo')->count());
    }

    public function test_el_mensaje_agrupa_por_modulo_y_cuenta_tambien_las_referencias_al_producto(): void
    {
        $this->sembrarCatalogoAnterior();
        $this->tablaAjena('existencias', 'id_producto', 'id_variacion');
        $this->tablaAjena('promociones', 'id_producto');
        $this->tablaAjena('items_carrito', 'id_variacion');
        DB::table('existencias')->insert(['id_producto' => 8, 'id_variacion' => 9]);
        DB::table('promociones')->insert(['id_producto' => 8]);
        DB::table('items_carrito')->insert(['id_variacion' => 9]);

        try {
            $this->sembrar();
            $this->fail('Debió detenerse.');
        } catch (RuntimeException $error) {
            $mensaje = $error->getMessage();

            $this->assertStringContainsString('M1: promociones.id_producto: 1 filas (ids 8)', $mensaje);
            $this->assertStringContainsString('M4: existencias.id_variacion: 1 filas (ids 9); existencias.id_producto: 1 filas (ids 8)', $mensaje);
            $this->assertStringContainsString('M5: items_carrito.id_variacion: 1 filas (ids 9)', $mensaje);
        }
    }

    public function test_con_la_variable_de_forzado_siembra(): void
    {
        $this->sembrarCatalogoAnterior();
        $this->tablaAjena('pedidos_lineas', 'id_variacion');
        DB::table('pedidos_lineas')->insert(['id_variacion' => 9]);
        $this->forzar('1');

        $this->sembrar();

        $this->assertSame('DP-BOT-TER-NEG', $this->skuDeLaVariacion(9));
        $this->assertSame(4, (int) DB::table('productos')->where('id_producto', 8)->value('id_comercio'));
        $this->assertSame(1, DB::table('pedidos_lineas')->count(), 'El seeder no escribe en tablas de otros módulos.');
    }

    public function test_no_se_detiene_por_referencias_a_ids_que_no_cambian(): void
    {
        $this->sembrarCatalogoAnterior();
        $this->tablaAjena('pedidos_lineas', 'id_variacion');
        $this->tablaAjena('productos_destacados', 'id_producto');
        DB::table('pedidos_lineas')->insert(['id_variacion' => 1]);
        DB::table('productos_destacados')->insert(['id_producto' => 1]);

        $this->sembrar();

        $this->assertSame('DP-BOT-TER-NEG', $this->skuDeLaVariacion(9));
    }

    public function test_reejecutar_sobre_los_datos_de_escenario_no_activa_el_freno(): void
    {
        $this->sembrar();
        $this->tablaAjena('pedidos_lineas', 'id_variacion');
        DB::table('pedidos_lineas')->insert(['id_variacion' => 9]);

        $this->sembrar();

        $this->assertSame('DP-BOT-TER-NEG', $this->skuDeLaVariacion(9));
    }

    public function test_tablas_y_columnas_ausentes_se_omiten_sin_error(): void
    {
        $this->sembrarCatalogoAnterior();
        // Ninguna tabla de otros módulos existe, salvo una sin la columna que se revisa.
        $this->tablaAjena('existencias', 'otra_columna');

        $this->sembrar();

        $this->assertSame('DP-BOT-TER-NEG', $this->skuDeLaVariacion(9));
    }
}
