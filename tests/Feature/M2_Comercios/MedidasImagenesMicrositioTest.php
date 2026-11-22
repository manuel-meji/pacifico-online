<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Models\M2_Comercios\BannerComercio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/**
 * Medidas mínimas de las imágenes del micrositio (logotipo, banners y galería):
 * las que no cumplen se rechazan con ERR-005 y un mensaje en español por campo,
 * y las guardadas antes se pueden conservar sin volver a subirlas.
 */
class MedidasImagenesMicrositioTest extends TestCase
{
    use PreparaM2;

    private function comoEncargado(): int
    {
        Storage::fake('public');
        $comercio = $this->crearComercio();
        $this->actingAs($this->usuario('encargado_comercio', $comercio->id_comercio), 'sanctum');

        return $comercio->id_comercio;
    }

    private function enviarBanner(int $id, int $ancho, int $alto): TestResponse
    {
        return $this->put("/api/m2/v1/comercios/{$id}/micrositio/banners", [
            'banners' => [[
                'imagen' => UploadedFile::fake()->image('banner.png', $ancho, $alto),
                'texto_alternativo' => 'Banner',
                'orden' => 1,
                'activo' => true,
            ]],
        ], ['Accept' => 'application/json']);
    }

    #[TestWith([1600, 400])]
    #[TestWith([1200, 600])]
    #[TestWith([1920, 600])]
    public function test_acepta_banners_horizontales_de_al_menos_1200_px(int $ancho, int $alto): void
    {
        $this->enviarBanner($this->comoEncargado(), $ancho, $alto)->assertOk();
    }

    #[TestWith([900, 300])] // angosto
    #[TestWith([1600, 1200])] // casi cuadrado
    #[TestWith([1200, 1600])] // vertical, como una foto de celular
    public function test_rechaza_banners_angostos_o_no_horizontales(int $ancho, int $alto): void
    {
        $id = $this->comoEncargado();

        $respuesta = $this->enviarBanner($id, $ancho, $alto)
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005');

        $this->assertSame(
            ['El banner debe ser horizontal (el ancho al menos el doble del alto) y medir al menos 1200 px de ancho.'],
            $respuesta->json('error.detalles')['banners.0.imagen']
        );

        $this->assertDatabaseMissing('banners_comercio', ['id_comercio' => $id]);
    }

    public function test_un_banner_guardado_se_conserva_sin_volver_a_subir_la_imagen(): void
    {
        $id = $this->comoEncargado();
        $this->enviarBanner($id, 1600, 400)->assertOk();
        $idBanner = BannerComercio::withoutGlobalScopes()
            ->where('id_comercio', $id)->value('id_banner');

        $this->put("/api/m2/v1/comercios/{$id}/micrositio/banners", [
            'banners' => [[
                'id_banner' => $idBanner,
                'texto_alternativo' => 'Banner con otro texto',
                'orden' => 1,
                'activo' => true,
            ]],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertDatabaseHas('banners_comercio', [
            'id_banner' => $idBanner,
            'texto_alternativo' => 'Banner con otro texto',
        ]);
    }

    public function test_la_galeria_exige_al_menos_600_por_400(): void
    {
        $id = $this->comoEncargado();
        $enviar = fn (int $ancho, int $alto) => $this->put("/api/m2/v1/comercios/{$id}/micrositio/galeria", [
            'galeria' => [[
                'imagen' => UploadedFile::fake()->image('foto.png', $ancho, $alto),
                'texto_alternativo' => 'Foto del local',
                'orden' => 1,
            ]],
        ], ['Accept' => 'application/json']);

        $respuesta = $enviar(500, 400)->assertStatus(422);
        $this->assertSame(
            ['Cada imagen de la galería debe medir al menos 600 × 400 px.'],
            $respuesta->json('error.detalles')['galeria.0.imagen']
        );

        $enviar(600, 800)->assertOk(); // la galería acepta fotos verticales
    }

    public function test_el_logotipo_debe_ser_casi_cuadrado_y_de_al_menos_200_px(): void
    {
        $id = $this->comoEncargado();
        $enviar = fn (int $ancho, int $alto) => $this->post("/api/m2/v1/comercios/{$id}/micrositio/logotipo", [
            'logotipo' => UploadedFile::fake()->image('logo.png', $ancho, $alto),
        ], ['Accept' => 'application/json']);

        $mensaje = 'El logotipo debe ser cuadrado o casi cuadrado y medir al menos 200 × 200 px.';
        $enviar(150, 150)->assertStatus(422)->assertJsonPath('error.detalles.logotipo.0', $mensaje);
        $enviar(800, 300)->assertStatus(422)->assertJsonPath('error.detalles.logotipo.0', $mensaje);
        $enviar(400, 400)->assertOk();
        $enviar(400, 300)->assertOk();
    }

    public function test_los_mensajes_de_tipo_y_peso_estan_en_espanol(): void
    {
        $id = $this->comoEncargado();

        $this->post("/api/m2/v1/comercios/{$id}/micrositio/logotipo", [
            'logotipo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.logotipo.0', 'El archivo debe ser una imagen.');

        $this->post("/api/m2/v1/comercios/{$id}/micrositio/logotipo", [
            'logotipo' => UploadedFile::fake()->image('logo.png', 400, 400)->size(3000),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.logotipo.0', 'La imagen no puede pesar más de 2 MB.');
    }

    public function test_los_mensajes_de_banners_y_galeria_estan_en_espanol(): void
    {
        $id = $this->comoEncargado();

        $respuesta = $this->put("/api/m2/v1/comercios/{$id}/micrositio/galeria", [
            'galeria' => [
                ['imagen' => UploadedFile::fake()->image('a.png', 800, 600), 'texto_alternativo' => '', 'orden' => 1],
                ['texto_alternativo' => str_repeat('a', 151), 'orden' => 2],
            ],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $detalles = $respuesta->json('error.detalles');
        $this->assertSame(['Describí qué se ve en la imagen: lo leen los lectores de pantalla y aparece si la imagen no carga.'], $detalles['galeria.0.texto_alternativo']);
        $this->assertSame(['Elegí una imagen.'], $detalles['galeria.1.imagen']);
        $this->assertSame(['La descripción de la imagen no puede pasar de 150 caracteres.'], $detalles['galeria.1.texto_alternativo']);

        $this->put("/api/m2/v1/comercios/{$id}/micrositio/banners", [
            'banners' => [['imagen' => UploadedFile::fake()->image('b.png', 1600, 400), 'texto_alternativo' => '', 'orden' => 1, 'activo' => true]],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('error.codigo', 'ERR-005');

        $this->post("/api/m2/v1/comercios/{$id}/micrositio/logotipo", [], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.logotipo.0', 'Elegí una imagen.');
    }
}
