<?php

namespace Tests\Feature\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Services\M4_Inventario\Dobles\InventarioDobleBase;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\M4_Inventario\InventarioService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * InventarioDobleBase: la clase que extienden los dobles de M4 de otros módulos
 * para que no se rompan cuando la interfaz pase a las 20 operaciones (SC-06).
 */
class InventarioDobleBaseTest extends TestCase
{
    public function test_un_doble_que_extiende_la_base_cumple_la_interfaz_y_sobrescribe_solo_lo_que_usa(): void
    {
        $doble = new class extends InventarioDobleBase
        {
            public function consultarExistencias(int $variacionId): int
            {
                return 7;
            }
        };

        $this->assertInstanceOf(InventarioModuleInterface::class, $doble);
        $this->assertSame(7, $doble->consultarExistencias(10));

        try {
            $doble->obtenerReserva('9edc54ec-a87f-42c2-b7a1-b635b3ecfe6d');
            $this->fail('Debía lanzar ERR-006.');
        } catch (ErrorInventarioException $e) {
            $this->assertSame('ERR-006', $e->codigo);
            $this->assertSame('M4', $e->detalles['modulo']);
        }
    }

    public function test_la_base_declara_las_mismas_firmas_publicas_que_el_servicio_de_m4(): void
    {
        $firmas = fn (string $clase): array => collect((new ReflectionClass($clase))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn (ReflectionMethod $m): bool => $m->isConstructor() || $m->isStatic())
            ->mapWithKeys(fn (ReflectionMethod $m): array => [$m->getName() => implode(', ', array_map(
                fn ($p): string => $p->getType().' $'.$p->getName().($p->isOptional() ? ' = opcional' : ''),
                $m->getParameters(),
            )).' : '.$m->getReturnType()])
            ->sortKeys()
            ->all();

        $this->assertSame($firmas(InventarioService::class), $firmas(InventarioDobleBase::class));
    }
}
