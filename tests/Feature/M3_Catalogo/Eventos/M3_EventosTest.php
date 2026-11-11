<?php

namespace Tests\Feature\M3_Catalogo\Eventos;

use App\Events\M3_Catalogo\ProductoPublicado;
use App\Exceptions\Nucleo\ErrorNucleoException;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Event;
use Tests\Feature\M3_Catalogo\EsquemaCatalogoPrueba;
use Tests\TestCase;

/** Evento ProductoPublicado v1.0.0: un evento por variación, solo al pasar a publicado. */
class M3_EventosTest extends TestCase
{
    use EsquemaCatalogoPrueba;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogo(1);
        // Solo se simula este evento; los eventos de Eloquent siguen funcionando.
        Event::fake([ProductoPublicado::class]);
    }

    /** @return array{0: int, 1: list<array<string, mixed>>} */
    private function productoListoParaPublicar(int $variaciones): array
    {
        $idProducto = $this->nuevoProducto();
        $creadas = [];

        for ($i = 1; $i <= $variaciones; $i++) {
            $creadas[] = $this->servicioCatalogo()->crearVariacion($idProducto, $this->datosVariacion("SKU-{$i}"));
        }

        $this->servicioCatalogo()->agregarImagen($idProducto, ['imagen_url' => 'https://ejemplo.test/foto.jpg']);

        return [$idProducto, $creadas];
    }

    public function test_al_publicar_un_producto_con_dos_variaciones_se_despachan_dos_eventos(): void
    {
        [$idProducto, $variaciones] = $this->productoListoParaPublicar(2);

        Event::assertNotDispatched(ProductoPublicado::class);

        $this->servicioCatalogo()->cambiarEstado($idProducto, ['estado_producto' => 'publicado']);

        Event::assertDispatchedTimes(ProductoPublicado::class, 2);

        foreach ($variaciones as $variacion) {
            Event::assertDispatched(
                ProductoPublicado::class,
                fn (ProductoPublicado $evento): bool => $evento->id_comercio === 1
                    && $evento->id_producto === $idProducto
                    && $evento->id_variacion === $variacion['id_variacion']
            );
        }
    }

    public function test_guardar_un_borrador_no_despacha_ningun_evento(): void
    {
        [$idProducto] = $this->productoListoParaPublicar(2);

        $this->servicioCatalogo()->cambiarEstado($idProducto, ['estado_producto' => 'borrador']);
        $this->servicioCatalogo()->cambiarEstado($idProducto, ['destacado' => true]);

        Event::assertNotDispatched(ProductoPublicado::class);
    }

    public function test_editar_un_producto_ya_publicado_sin_cambiar_el_estado_no_despacha_eventos(): void
    {
        [$idProducto] = $this->productoListoParaPublicar(1);

        $this->servicioCatalogo()->cambiarEstado($idProducto, ['estado_producto' => 'publicado']);
        Event::assertDispatchedTimes(ProductoPublicado::class, 1);

        $this->servicioCatalogo()->cambiarEstado($idProducto, ['destacado' => true]);
        $this->servicioCatalogo()->cambiarEstado($idProducto, ['estado_producto' => 'publicado']);

        Event::assertDispatchedTimes(ProductoPublicado::class, 1);
    }

    public function test_una_publicacion_rechazada_no_despacha_eventos(): void
    {
        $idProducto = $this->nuevoProducto();

        try {
            $this->servicioCatalogo()->cambiarEstado($idProducto, ['estado_producto' => 'publicado']);
            $this->fail('Debió rechazar la publicación.');
        } catch (ErrorNucleoException) {
            // esperado: falta variación e imagen
        }

        Event::assertNotDispatched(ProductoPublicado::class);
    }

    public function test_el_payload_cumple_el_contrato_acordado_con_m4(): void
    {
        $evento = new ProductoPublicado(4, 8, 9);
        $payload = $evento->payload();

        $this->assertSame(
            ['id_evento', 'fecha_evento', 'id_comercio', 'id_producto', 'id_variacion'],
            array_keys($payload)
        );
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $payload['id_evento']
        );
        $this->assertNotFalse(\DateTimeImmutable::createFromFormat(DATE_ATOM, $payload['fecha_evento']));
        $this->assertSame(4, $payload['id_comercio']);
        $this->assertSame(8, $payload['id_producto']);
        $this->assertSame(9, $payload['id_variacion']);
    }

    public function test_el_evento_se_entrega_despues_de_confirmar_la_transaccion(): void
    {
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, new ProductoPublicado(1, 1, 1));
    }
}