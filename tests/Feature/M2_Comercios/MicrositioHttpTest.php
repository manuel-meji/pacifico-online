<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

class MicrositioHttpTest extends TestCase
{
    use PreparaM2;

    public function test_encargado_de_otro_comercio_no_puede_editar_micrositio(): void
    {
        $comercioObjetivo = $this->crearComercio();
        $otroComercio = $this->crearComercio();

        $usuario = $this->usuario(
            'encargado_comercio',
            $otroComercio->id_comercio
        );

        $respuesta = $this
            ->actingAs($usuario, 'sanctum')
            ->putJson(
                "/api/m2/v1/comercios/{$comercioObjetivo->id_comercio}/micrositio",
                [
                    'descripcion' => 'Descripción que no debería guardarse.',
                ]
            );

        $respuesta
            ->assertStatus(403)
            ->assertJsonPath('error.codigo', 'ERR-002');

        $this->assertDatabaseMissing('micrositios', [
            'id_comercio' => $comercioObjetivo->id_comercio,
            'descripcion' => 'Descripción que no debería guardarse.',
        ]);
    }

    public function test_encargado_del_comercio_puede_actualizar_micrositio(): void
    {
        $comercio = $this->crearComercio();

        $usuario = $this->usuario(
            'encargado_comercio',
            $comercio->id_comercio
        );

        $respuesta = $this
            ->actingAs($usuario, 'sanctum')
            ->putJson(
                "/api/m2/v1/comercios/{$comercio->id_comercio}/micrositio",
                [
                    'descripcion' => 'Información actualizada del comercio.',
                ]
            );

        $respuesta->assertOk();

        $this->assertDatabaseHas('micrositios', [
            'id_comercio' => $comercio->id_comercio,
            'descripcion' => 'Información actualizada del comercio.',
        ]);
    }
}