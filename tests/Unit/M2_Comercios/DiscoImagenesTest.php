<?php

declare(strict_types=1);

namespace Tests\Unit\M2_Comercios;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M2_Comercios\Micrositio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/** Las imágenes de M2 van al disco configurado en config/m2.php (local o Supabase Storage). */
class DiscoImagenesTest extends TestCase
{
    use PreparaM2;

    public function test_con_disco_s3_el_logotipo_se_guarda_en_supabase_y_el_anterior_se_borra(): void
    {
        config(['m2.disco_imagenes' => 's3']);
        Storage::fake('s3');
        Storage::fake('public');
        $comercio = $this->crearComercio();
        $this->enAmbitoDe($comercio->id_comercio);
        $servicio = app(ComerciosService::class);

        $servicio->guardarLogotipo($comercio->id_comercio, UploadedFile::fake()->image('logo.png'));
        $primero = Storage::disk('s3')->allFiles("m2/comercios/{$comercio->id_comercio}/logotipo");

        $this->assertCount(1, $primero);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertStringContainsString($primero[0], (string) Micrositio::query()->value('logotipo'));
    }

    public function test_al_quitar_un_banner_se_borra_su_archivo_del_disco_configurado(): void
    {
        config(['m2.disco_imagenes' => 's3']);
        Storage::fake('s3');
        $comercio = $this->crearComercio();
        $this->enAmbitoDe($comercio->id_comercio);
        $servicio = app(ComerciosService::class);

        $servicio->gestionarBanners($comercio->id_comercio, [
            ['imagen' => UploadedFile::fake()->image('b.png'), 'orden' => 1, 'texto_alternativo' => 'Banner', 'activo' => true],
        ]);
        $this->assertCount(1, Storage::disk('s3')->allFiles('m2'));

        $servicio->gestionarBanners($comercio->id_comercio, []);

        $this->assertSame([], Storage::disk('s3')->allFiles('m2'));
    }

    public function test_si_el_disco_no_guarda_la_imagen_lanza_err_006_y_el_logotipo_no_cambia(): void
    {
        config(['m2.disco_imagenes' => 's3']);
        Storage::fake('s3');
        $comercio = $this->crearComercio();
        $this->enAmbitoDe($comercio->id_comercio);
        $servicio = app(ComerciosService::class);
        $servicio->guardarLogotipo($comercio->id_comercio, UploadedFile::fake()->image('logo.png'));
        $logotipoAnterior = Micrositio::query()->value('logotipo');

        // Disco que falla en silencio, como el s3 con 'throw' => false.
        $discoQueFalla = \Mockery::mock(FilesystemAdapter::class);
        $discoQueFalla->shouldReceive('putFile')->andReturn(false);
        $discoQueFalla->shouldNotReceive('url');
        Storage::set('s3', $discoQueFalla);

        try {
            $servicio->guardarLogotipo($comercio->id_comercio, UploadedFile::fake()->image('otro.png'));
            $this->fail('Se esperaba ErrorNucleoException ERR-006.');
        } catch (ErrorNucleoException $e) {
            $this->assertSame('ERR-006', $e->getCodigo());
            $this->assertSame(503, $e->getCodigoHttp());
        }

        $this->assertSame($logotipoAnterior, Micrositio::query()->value('logotipo'));
    }

    public function test_el_borrado_entiende_las_urls_publicas_de_supabase(): void
    {
        config(['m2.disco_imagenes' => 's3']);
        Storage::fake('s3');
        Storage::disk('s3')->put('m2/comercios/1/banners/a.png', 'x');
        $eliminar = new \ReflectionMethod(ComerciosService::class, 'eliminarArchivoPublico');

        $eliminar->invoke(app(ComerciosService::class),
            'https://abc.supabase.co/storage/v1/object/public/imagenes/m2/comercios/1/banners/a.png');

        $this->assertFalse(Storage::disk('s3')->exists('m2/comercios/1/banners/a.png'));
    }
}
