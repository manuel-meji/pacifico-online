<?php

namespace Tests\Unit\M3_Catalogo;

use App\Exceptions\M3_Catalogo\ErrorCatalogoException;
use App\Http\Requests\M3_Catalogo\AgregarImagenRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\Feature\M3_Catalogo\EsquemaCatalogoPrueba;
use Tests\TestCase;

/** RN-M3-09: imágenes JPG o PNG, de hasta 5 MB y máximo 8 por producto. */
class ImagenInvalidaRechazadaTest extends TestCase
{
    use EsquemaCatalogoPrueba;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogo(1);
    }

    /** @return array<string, list<string>> */
    private function errores(array $datos): array
    {
        $solicitud = new AgregarImagenRequest;

        return Validator::make($datos, $solicitud->rules(), $solicitud->messages())->errors()->toArray();
    }

    public function test_un_archivo_que_no_es_jpg_ni_png_es_rechazado(): void
    {
        $errores = $this->errores([
            'archivo' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'),
        ]);

        $this->assertArrayHasKey('archivo', $errores);
        $this->assertSame('La imagen debe ser JPG o PNG.', $errores['archivo'][0]);
    }

    public function test_una_imagen_de_mas_de_5_mb_es_rechazada(): void
    {
        $errores = $this->errores([
            'archivo' => UploadedFile::fake()->create('foto.jpg', 6000, 'image/jpeg'),
        ]);

        $this->assertArrayHasKey('archivo', $errores);
        $this->assertSame('La imagen no puede pesar más de 5 MB.', $errores['archivo'][0]);
    }

    public function test_un_jpg_y_un_png_dentro_del_limite_son_aceptados(): void
    {
        $this->assertSame([], $this->errores([
            'archivo' => UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg'),
        ]));
        $this->assertSame([], $this->errores([
            'archivo' => UploadedFile::fake()->create('logo.png', 5000, 'image/png'),
        ]));
    }

    public function test_un_enlace_que_no_termina_en_jpg_o_png_es_rechazado(): void
    {
        $errores = $this->errores(['imagen_url' => 'https://ejemplo.test/documento.pdf']);

        $this->assertArrayHasKey('imagen_url', $errores);
    }

    public function test_la_novena_imagen_es_rechazada(): void
    {
        $idProducto = $this->nuevoProducto();

        for ($i = 1; $i <= 8; $i++) {
            $this->servicioCatalogo()->agregarImagen($idProducto, ['imagen_url' => "https://ejemplo.test/foto-{$i}.jpg"]);
        }

        try {
            $this->servicioCatalogo()->agregarImagen($idProducto, ['imagen_url' => 'https://ejemplo.test/foto-9.jpg']);
            $this->fail('Debió rechazar la novena imagen.');
        } catch (ErrorCatalogoException $error) {
            $this->assertSame('ERR-M3-003', $error->codigo);
            $this->assertSame(409, $error->http);
        }

        $this->assertSame(8, DB::table('imagenes_producto')->where('id_producto', $idProducto)->count());
    }
}