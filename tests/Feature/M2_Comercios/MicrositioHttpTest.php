<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Models\M2_Comercios\BannerComercio;
use App\Models\M2_Comercios\ImagenGaleriaComercio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_listas_vacias_eliminan_banners_galeria_y_sus_archivos(): void
    {
        Storage::fake('public');

        $comercio = $this->crearComercio();

        $usuario = $this->usuario(
            'encargado_comercio',
            $comercio->id_comercio
        );

        $this->actingAs($usuario, 'sanctum');

        $respuestaCrearBanner = $this->put(
            "/api/m2/v1/comercios/{$comercio->id_comercio}/micrositio/banners",
            [
                'banners' => [
                    [
                        'imagen' => UploadedFile::fake()->image('banner.png'),
                        'texto_alternativo' => 'Banner de prueba',
                        'orden' => 1,
                        'activo' => true,
                    ],
                ],
            ]
        );

        $respuestaCrearGaleria = $this->put(
            "/api/m2/v1/comercios/{$comercio->id_comercio}/micrositio/galeria",
            [
                'galeria' => [
                    [
                        'imagen' => UploadedFile::fake()->image('galeria.png'),
                        'texto_alternativo' => 'Imagen de prueba',
                        'orden' => 1,
                    ],
                ],
            ]
        );

        $respuestaCrearBanner->assertOk();
        $respuestaCrearGaleria->assertOk();

        $banner = BannerComercio::withoutGlobalScopes()
            ->where('id_comercio', $comercio->id_comercio)
            ->firstOrFail();

        $imagenGaleria = ImagenGaleriaComercio::withoutGlobalScopes()
            ->where('id_comercio', $comercio->id_comercio)
            ->firstOrFail();

        $rutaBanner = parse_url($banner->ruta, PHP_URL_PATH);
        $rutaGaleria = parse_url($imagenGaleria->ruta, PHP_URL_PATH);

        $this->assertIsString($rutaBanner);
        $this->assertIsString($rutaGaleria);

        $rutaBanner = ltrim($rutaBanner, '/');
        $rutaGaleria = ltrim($rutaGaleria, '/');

        if (str_starts_with($rutaBanner, 'storage/')) {
            $rutaBanner = substr($rutaBanner, strlen('storage/'));
        }

        if (str_starts_with($rutaGaleria, 'storage/')) {
            $rutaGaleria = substr($rutaGaleria, strlen('storage/'));
        }

        Storage::disk('public')->assertExists($rutaBanner);
        Storage::disk('public')->assertExists($rutaGaleria);

        $respuestaBanners = $this->putJson(
            "/api/m2/v1/comercios/{$comercio->id_comercio}/micrositio/banners",
            [
                'banners' => [],
            ]
        );

        $respuestaGaleria = $this->putJson(
            "/api/m2/v1/comercios/{$comercio->id_comercio}/micrositio/galeria",
            [
                'galeria' => [],
            ]
        );

        $respuestaBanners->assertOk();
        $respuestaGaleria->assertOk();

        $this->assertDatabaseMissing('banners_comercio', [
            'id_comercio' => $comercio->id_comercio,
        ]);

        $this->assertDatabaseMissing('galeria_comercio', [
            'id_comercio' => $comercio->id_comercio,
        ]);

        Storage::disk('public')->assertMissing($rutaBanner);
        Storage::disk('public')->assertMissing($rutaGaleria);
    }
}
