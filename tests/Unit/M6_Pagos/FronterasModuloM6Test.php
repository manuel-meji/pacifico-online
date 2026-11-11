<?php

declare(strict_types=1);

namespace Tests\Unit\M6_Pagos;

use PHPUnit\Framework\TestCase;

/**
 * Reglas 1 y 2 del proyecto: M6 no importa modelos Eloquent ajenos ni consulta
 * tablas de otros módulos; solo habla con ellos por app/Contracts/M*.
 */
class FronterasModuloM6Test extends TestCase
{
    private const MODELOS_AJENOS = 'App\\\\Models\\\\(M1_Portal|M2_Comercios|M3_Catalogo|M4_Inventario|M5_Pedidos|M7_Contabilidad)\\\\';

    private const TABLAS_AJENAS = '(clientes|comercios|productos|variaciones|existencias|reservas|pedidos_maestros|pedidos_subpedidos|pedidos_lineas|diario_contable)';

    /** @return list<string> */
    private function archivosDeM6(): array
    {
        $raiz = dirname(__DIR__, 3);
        $archivos = [];

        foreach (['app/Services/M6_Pagos', 'app/Http/Controllers/M6_Pagos', 'tests/Doubles/M6'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$raiz/$dir", \FilesystemIterator::SKIP_DOTS)) as $f) {
                if ($f->getExtension() === 'php') {
                    $archivos[] = $f->getPathname();
                }
            }
        }

        foreach (['M1/FakeClienteClient', 'M2/FakeComercioClient', 'M2/ComerciosFiscalFake', 'M5/FakePedidoClient', 'M5/PedidosContratoFake', 'M7/FakeContabilidadClient'] as $doble) {
            $archivos[] = "$raiz/tests/Doubles/$doble.php";
        }

        return $archivos;
    }

    public function test_m6_y_sus_dobles_no_importan_modelos_de_otros_modulos(): void
    {
        foreach ($this->archivosDeM6() as $archivo) {
            $this->assertDoesNotMatchRegularExpression('/'.self::MODELOS_AJENOS.'/', file_get_contents($archivo), $archivo);
        }
    }

    public function test_m6_y_sus_dobles_no_consultan_tablas_de_otros_modulos(): void
    {
        $patron = '/(DB::table|->from|->join|->leftJoin)\(\s*[\'"]'.self::TABLAS_AJENAS.'[\'"]/';

        foreach ($this->archivosDeM6() as $archivo) {
            $this->assertDoesNotMatchRegularExpression($patron, file_get_contents($archivo), $archivo);
        }
    }
}
