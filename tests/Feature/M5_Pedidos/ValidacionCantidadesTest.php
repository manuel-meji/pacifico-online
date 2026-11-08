<?php

namespace Tests\Feature\M5_Pedidos;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Services\M5_Pedidos\Dobles\CatalogoStub;
use App\Services\M5_Pedidos\Dobles\ComerciosStub;
use App\Services\M5_Pedidos\Dobles\InventarioStub;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CP-004: el carrito rechaza cantidades iguales a cero o negativas (RF-001, RF-002).
 * La validación vive en ControladorCarrito, por eso la prueba va por HTTP.
 */
class ValidacionCantidadesTest extends TestCase
{
    private const ID_CLIENTE = 71;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/M5_Pedidos',
            '--realpath' => false,
        ]);

        $this->app->instance(CatalogoServiceInterface::class, new CatalogoStub);
        $this->app->instance(InventarioServiceInterface::class, new InventarioStub);
        $this->app->instance(ComerciosServiceInterface::class, new ComerciosStub);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function cantidadesInvalidas(): array
    {
        return [
            'cero' => [0],
            'negativa' => [-3],
        ];
    }

    #[DataProvider('cantidadesInvalidas')]
    public function test_cp004_rechaza_agregar_un_item_con_cantidad_cero_o_negativa(int $cantidad): void
    {
        $this->postJson('/m5/carrito/items', [
            'id_cliente' => self::ID_CLIENTE,
            'id_variacion' => 2,
            'cantidad' => $cantidad,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005');

        $this->assertDatabaseCount('items_carrito', 0);
    }

    #[DataProvider('cantidadesInvalidas')]
    public function test_cp004_rechaza_actualizar_un_item_a_cantidad_cero_o_negativa(int $cantidad): void
    {
        $this->postJson('/m5/carrito/items', [
            'id_cliente' => self::ID_CLIENTE,
            'id_variacion' => 2,
            'cantidad' => 2,
        ])->assertStatus(201);

        $idItem = ItemCarrito::first()->id_item_carrito;

        $this->putJson('/m5/carrito/items/'.$idItem, ['cantidad' => $cantidad])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005');

        $this->assertDatabaseHas('items_carrito', [
            'id_item_carrito' => $idItem,
            'cantidad' => 2,
        ]);
    }
}
