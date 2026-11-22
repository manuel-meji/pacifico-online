<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Events\M2_Comercios\ComercioAprobado;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\ContactoComercio;
use App\Models\M2_Comercios\DocumentoComercio;
use App\Models\M2_Comercios\Micrositio;
use App\Models\M2_Comercios\PublicidadComercio;
use App\Models\M2_Comercios\SolicitudComercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

/**
 * Revisión exhaustiva de M2 (21 y 22/11): cada prueba cubre un hallazgo de la tabla del documento
 * «Revisión exhaustiva de M2». El número del hallazgo va en el nombre o en el comentario.
 */
class RevisionExhaustivaTest extends TestCase
{
    use PreparaM2;

    private const SOLICITUD = [
        'nombre_comercial' => 'Soda La Pacífica',
        'cedula_juridica' => '3-101-765432',
        'razon_social' => 'Soda La Pacífica S.A.',
        'direccion_fiscal' => 'Liberia, Guanacaste, 200 m norte del parque',
        'inscrito_como_contribuyente' => true,
        'nombre_encargado' => 'María Pérez Solano',
        'correo_encargado' => 'maria@sodalapacifica.cr',
        'telefono_encargado' => '8888-1234',
        'contrasena_encargado' => 'MiClave2026',
        'contrasena_encargado_confirmation' => 'MiClave2026',
    ];

    private function identidadQueCreaCuentas(): void
    {
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('existeCorreo')->andReturn(false);
            $mock->shouldReceive('crearCuenta')->andReturnUsing(fn (array $datos, $rol, $idComercio) => $this->usuario('encargado_comercio', $idComercio));
        });
    }

    private function registrar(array $cambios): TestResponse
    {
        return $this->postJson('/api/m2/v1/solicitudes', array_merge(self::SOLICITUD, $cambios));
    }

    // --- Hallazgos 1, 6 y 7: validación de la solicitud en español y por contenido ---

    public function test_hallazgo_1_los_campos_vacios_responden_en_espanol_con_el_nombre_del_campo(): void
    {
        $this->identidadQueCreaCuentas();
        $this->mock(NucleoServiceInterface::class);

        $detalles = $this->registrar(['nombre_comercial' => '', 'razon_social' => '', 'correo_encargado' => 'no-es-correo'])
            ->assertStatus(422)->json('error.detalles');

        $this->assertSame(['Completá el nombre comercial.'], $detalles['nombre_comercial']);
        $this->assertSame(['Completá la razón social.'], $detalles['razon_social']);
        $this->assertSame(['Escribí un correo válido, por ejemplo nombre@dominio.com.'], $detalles['correo_encargado']);
        $this->assertDatabaseCount('comercios', 0);
    }

    public function test_hallazgos_6_y_7_valida_formato_y_contenido_de_cada_dato(): void
    {
        $this->identidadQueCreaCuentas();
        $this->mock(NucleoServiceInterface::class);

        $detalles = $this->registrar([
            'cedula_juridica' => '2-123-123456',
            'nombre_comercial' => '222',
            'razon_social' => str_repeat('a', 151),
            'direccion_fiscal' => 'Cañas',
            'nombre_encargado' => 'Diego123',
            'telefono_encargado' => '1234',
        ])->assertStatus(422)->json('error.detalles');

        $this->assertSame(['La cédula jurídica debe tener el formato 3-XXX-XXXXXX, por ejemplo 3-101-123456.'], $detalles['cedula_juridica']);
        $this->assertSame(['El nombre comercial debe tener al menos una letra.'], $detalles['nombre_comercial']);
        $this->assertSame(['La razón social no puede pasar de 150 caracteres.'], $detalles['razon_social']);
        $this->assertStringContainsString('dirección fiscal completa', $detalles['direccion_fiscal'][0]);
        $this->assertSame(['El nombre solo puede tener letras y espacios.'], $detalles['nombre_encargado']);
        $this->assertSame(['Escribí un teléfono de Costa Rica de 8 dígitos, por ejemplo 8888-8888.'], $detalles['telefono_encargado']);
    }

    public function test_hallazgos_5_y_25_acepta_la_constancia_de_hacienda_y_no_repite_tipos(): void
    {
        Storage::fake('local');
        $this->identidadQueCreaCuentas();
        $this->mock(NucleoServiceInterface::class);
        $documento = fn (string $tipo) => [
            'tipo' => $tipo, 'fecha_vencimiento' => now()->addYear()->toDateString(),
            'archivo' => UploadedFile::fake()->create("{$tipo}.pdf", 10, 'application/pdf'),
        ];

        $this->post('/api/m2/v1/solicitudes', self::SOLICITUD + ['documentos' => [$documento('cedula_juridica'), $documento('cedula_juridica')]], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles', fn (array $d) => ($d['documentos.1.tipo'][0] ?? '') === 'Ya agregaste un documento de ese tipo; cada tipo va una sola vez.');

        $this->post('/api/m2/v1/solicitudes', self::SOLICITUD + ['documentos' => [$documento('constancia_hacienda')]], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertSame('constancia_hacienda', DocumentoComercio::query()->sole()->tipo);
    }

    // --- Hallazgo 11: la plataforma registra el comercio en su nombre ---

    public function test_hallazgo_11_la_plataforma_registra_sin_contrasena_y_queda_quien_lo_hizo(): void
    {
        $recibido = [];
        $this->mock(ServicioIdentidadInterface::class, function (MockInterface $mock) use (&$recibido): void {
            $mock->shouldReceive('existeCorreo')->andReturn(false);
            $mock->shouldReceive('crearCuenta')->once()->andReturnUsing(function (array $datos, $rol, $idComercio) use (&$recibido) {
                $recibido = $datos;

                return $this->usuario('encargado_comercio', $idComercio);
            });
        });
        $this->mock(NucleoServiceInterface::class)->shouldReceive('registrarAuditoria')->once()
            ->with('registrar_solicitud_comercio', 'solicitud_comercio', \Mockery::type('int'), \Mockery::type('array'));

        $datos = array_diff_key(self::SOLICITUD, array_flip(['contrasena_encargado', 'contrasena_encargado_confirmation']));
        $id = $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson('/api/m2/v1/solicitudes', $datos)->assertOk()->json('id_solicitud');

        $this->assertArrayNotHasKey('contrasena_cifrada', $recibido); // el núcleo envía la invitación
        $this->getJson("/api/m2/v1/solicitudes/{$id}")->assertOk()->assertJsonPath('nombre_registrador', 'Prueba');
    }

    // --- Hallazgos 15, 20 y 24: historial de la revisión y solicitud ya revisada ---

    public function test_hallazgos_15_20_y_24_guarda_quien_reviso_conserva_el_motivo_y_avisa_si_ya_se_reviso(): void
    {
        Event::fake([ComercioAprobado::class]);
        $this->mock(ServicioIdentidadInterface::class)->shouldNotReceive('crearCuenta');
        $this->mock(NucleoServiceInterface::class)->shouldReceive('registrarAuditoria');
        $comercio = $this->crearComercio(['estado' => Comercio::PENDIENTE]);
        $solicitud = $comercio->solicitudes()->create([
            'nombre_encargado' => 'Persona', 'correo_encargado' => 'persona@example.test', 'requiere_documentos' => false, 'cuenta_creada' => true,
            'estado' => SolicitudComercio::PENDIENTE,
        ]);
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum');

        $this->postJson("/api/m2/v1/solicitudes/{$solicitud->id_solicitud}/rechazar", ['motivo' => 'Corto'])
            ->assertStatus(422)->assertJsonPath('error.detalles.motivo.0', 'Explicá el motivo con un poco más de detalle (al menos 10 caracteres).');
        $this->postJson("/api/m2/v1/solicitudes/{$solicitud->id_solicitud}/rechazar", ['motivo' => 'No está inscrito en Hacienda.'])->assertOk();
        $rechazada = $this->getJson("/api/m2/v1/solicitudes/{$solicitud->id_solicitud}")->assertOk();
        $rechazada->assertJsonPath('nombre_revisor', 'Prueba')->assertJsonPath('ultimo_motivo_rechazo', 'No está inscrito en Hacienda.');
        $this->assertNotNull($rechazada->json('fecha_revision'));

        // La corrección limpia el motivo vigente, pero el último motivo queda para el historial.
        $solicitud->fresh()->forceFill(['estado' => SolicitudComercio::PENDIENTE, 'motivo_rechazo' => null, 'fecha_reenvio' => now()])->save();
        $this->postJson("/api/m2/v1/solicitudes/{$solicitud->id_solicitud}/aprobar")->assertOk();
        $this->getJson("/api/m2/v1/solicitudes/{$solicitud->id_solicitud}")->assertOk()
            ->assertJsonPath('estado', SolicitudComercio::APROBADA)
            ->assertJsonPath('ultimo_motivo_rechazo', 'No está inscrito en Hacienda.');

        $this->postJson("/api/m2/v1/solicitudes/{$solicitud->id_solicitud}/aprobar")
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.estado.0', 'Esta solicitud ya fue aprobada en otra sesión. Se actualizó la pantalla con su estado.');
    }

    public function test_hallazgo_13_el_error_de_documentos_dice_que_falta(): void
    {
        $this->mock(ServicioIdentidadInterface::class)->shouldNotReceive('crearCuenta');
        $this->mock(NucleoServiceInterface::class)->shouldNotReceive('registrarAuditoria');
        $comercio = $this->crearComercio(['estado' => Comercio::PENDIENTE]);
        $solicitud = $comercio->solicitudes()->create([
            'nombre_encargado' => 'Persona', 'correo_encargado' => 'persona@example.test', 'requiere_documentos' => true, 'cuenta_creada' => true,
            'estado' => SolicitudComercio::PENDIENTE,
        ]);
        foreach (['pendiente', 'invalido'] as $i => $resultado) {
            DocumentoComercio::create([
                'id_comercio' => $comercio->id_comercio, 'tipo' => $i === 0 ? 'patente' : 'cedula_juridica', 'ruta_cifrada' => "m2/x{$i}.enc",
                'fecha_vencimiento' => now()->addYear()->toDateString(), 'resultado' => $resultado,
            ]);
        }

        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum')
            ->postJson("/api/m2/v1/solicitudes/{$solicitud->id_solicitud}/aprobar")
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.documentos.0', 'Para aprobar falta: revisar 1 documento; 1 documento está inválido.');
    }

    // --- Hallazgo 17: corrección con un campo vaciado ---

    public function test_hallazgo_17_vaciar_un_campo_en_la_correccion_pide_completarlo_en_espanol(): void
    {
        $comercio = $this->crearComercio(['estado' => Comercio::RECHAZADO]);
        $comercio->solicitudes()->create([
            'nombre_encargado' => 'Persona', 'correo_encargado' => 'persona@example.test', 'requiere_documentos' => false,
            'cuenta_creada' => true, 'estado' => SolicitudComercio::RECHAZADA, 'motivo_rechazo' => 'Falta algo importante.',
        ]);

        $this->actingAs($this->usuario('encargado_comercio', $comercio->id_comercio), 'sanctum')
            ->postJson('/api/m2/v1/mi-solicitud/correccion', ['nombre_comercial' => '', 'razon_social' => ''])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.nombre_comercial.0', 'Completá el nombre comercial.')
            ->assertJsonPath('error.detalles.razon_social.0', 'Completá la razón social.');
    }

    public function test_hallazgo_25_en_la_correccion_un_documento_nuevo_reemplaza_al_del_mismo_tipo(): void
    {
        Storage::fake('local');
        $this->mock(ServicioIdentidadInterface::class);
        $comercio = $this->crearComercio(['estado' => Comercio::RECHAZADO]);
        $comercio->solicitudes()->create([
            'nombre_encargado' => 'Persona', 'correo_encargado' => 'persona@example.test', 'requiere_documentos' => true,
            'cuenta_creada' => true, 'estado' => SolicitudComercio::RECHAZADA, 'motivo_rechazo' => 'Patente ilegible, súbala otra vez.',
        ]);
        Storage::disk('local')->put('m2/documentos/viejo.enc', 'cifrado');
        DocumentoComercio::create([
            'id_comercio' => $comercio->id_comercio, 'tipo' => 'patente', 'ruta_cifrada' => 'm2/documentos/viejo.enc',
            'fecha_vencimiento' => now()->addYear()->toDateString(), 'resultado' => 'valido',
        ]);

        $this->actingAs($this->usuario('encargado_comercio', $comercio->id_comercio), 'sanctum')
            ->post('/api/m2/v1/mi-solicitud/correccion', ['documentos' => [[
                'tipo' => 'patente', 'fecha_vencimiento' => now()->addYears(2)->toDateString(),
                'archivo' => UploadedFile::fake()->create('patente.pdf', 10, 'application/pdf'),
            ]]], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame(1, DocumentoComercio::query()->where('tipo', 'patente')->count());
        $this->assertSame('pendiente', DocumentoComercio::query()->sole()->resultado);
        Storage::disk('local')->assertMissing('m2/documentos/viejo.enc');
    }

    // --- Micrositio ---

    private function comoEncargadoDe(?Comercio $comercio = null): int
    {
        Storage::fake('public');
        $comercio ??= $this->crearComercio();
        $this->actingAs($this->usuario('encargado_comercio', $comercio->id_comercio), 'sanctum');

        return $comercio->id_comercio;
    }

    public function test_hallazgo_21_el_logotipo_se_puede_quitar(): void
    {
        $id = $this->comoEncargadoDe();
        $this->post("/api/m2/v1/comercios/{$id}/micrositio/logotipo", [
            'logotipo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertNotNull($this->comoSistema(fn () => Micrositio::query()->where('id_comercio', $id)->value('logotipo')));

        $this->deleteJson("/api/m2/v1/comercios/{$id}/micrositio/logotipo")->assertOk();

        $this->assertNull($this->comoSistema(fn () => Micrositio::query()->where('id_comercio', $id)->value('logotipo')));
        $this->assertNull(app(ComerciosService::class)->obtenerDatosComercioPublico($id)['logo_url']);
    }

    public function test_hallazgo_22_la_descripcion_vacia_se_quita_y_una_muy_corta_se_rechaza(): void
    {
        $id = $this->comoEncargadoDe();

        $this->putJson("/api/m2/v1/comercios/{$id}/micrositio", ['descripcion' => 'Hola'])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles.descripcion.0', 'Escribí una descripción de al menos 10 caracteres, o dejala vacía para no mostrar ninguna.');
        $this->putJson("/api/m2/v1/comercios/{$id}/micrositio", ['descripcion' => str_repeat('a', 2001)])->assertStatus(422);
        $this->putJson("/api/m2/v1/comercios/{$id}/micrositio", ['descripcion' => ''])->assertOk();
        $this->assertNull($this->comoSistema(fn () => Micrositio::query()->where('id_comercio', $id)->value('descripcion')));
    }

    public function test_hallazgo_30_la_descripcion_de_la_imagen_debe_decir_algo(): void
    {
        $id = $this->comoEncargadoDe();

        $this->put("/api/m2/v1/comercios/{$id}/micrositio/galeria", ['galeria' => [[
            'imagen' => UploadedFile::fake()->image('foto.png', 800, 600), 'texto_alternativo' => 'kjk', 'orden' => 1,
        ]]], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles', fn (array $d) => str_starts_with($d['galeria.0.texto_alternativo'][0] ?? '', 'Describí la imagen con al menos 5'));
    }

    public function test_hallazgo_31_el_dia_abierto_sin_horas_marca_los_campos_que_faltan(): void
    {
        $id = $this->comoEncargadoDe();
        $semana = array_map(fn (int $dia) => ['dia_semana' => $dia, 'cerrado' => $dia > 1, 'hora_apertura' => null, 'hora_cierre' => null], range(1, 7));
        $semana[0]['hora_apertura'] = '08:00';

        $this->putJson("/api/m2/v1/comercios/{$id}/micrositio/horarios", ['horarios' => $semana])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles', fn (array $d) => isset($d['horarios.0.hora_cierre']) && ! isset($d['horarios.0.hora_apertura']) && ! isset($d['horarios.0']))
            ->assertJsonPath('error.detalles', fn (array $d) => $d['horarios.0.hora_cierre'][0] === 'Indicá la hora de apertura y de cierre, o marcá el día como cerrado.');
    }

    public function test_hallazgo_32_los_contactos_se_validan_y_se_guardan_normalizados(): void
    {
        $id = $this->comoEncargadoDe();

        $this->putJson("/api/m2/v1/comercios/{$id}/micrositio/contactos", ['contactos' => [
            ['tipo' => 'telefono', 'valor' => 'diego123'],
            ['tipo' => 'instagram', 'valor' => 'https://facebook.com/algo'],
        ]])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles', fn (array $d) => isset($d['contactos.0.valor'], $d['contactos.1.valor'])
                && str_starts_with($d['contactos.1.valor'][0], 'Ese enlace no es de Instagram.'));

        $this->putJson("/api/m2/v1/comercios/{$id}/micrositio/contactos", ['contactos' => [
            ['tipo' => 'telefono', 'valor' => '+506 8888 1234'],
            ['tipo' => 'instagram', 'valor' => '@soda'],
            ['tipo' => 'whatsapp', 'valor' => '8888-1234'],
        ]])->assertOk();

        $valores = $this->comoSistema(fn () => ContactoComercio::query()->where('id_comercio', $id)->pluck('valor', 'tipo')->all());
        $this->assertSame(['telefono' => '8888-1234', 'instagram' => 'https://instagram.com/soda', 'whatsapp' => 'https://wa.me/50688881234'], $valores);

        // Una lista vacía quita todos los contactos.
        $this->putJson("/api/m2/v1/comercios/{$id}/micrositio/contactos", ['contactos' => []])->assertOk();
        $this->assertSame(0, $this->comoSistema(fn () => ContactoComercio::query()->where('id_comercio', $id)->count()));
    }

    public function test_hallazgo_33_un_contacto_de_cada_tipo(): void
    {
        $id = $this->comoEncargadoDe();

        $this->putJson("/api/m2/v1/comercios/{$id}/micrositio/contactos", ['contactos' => [
            ['tipo' => 'telefono', 'valor' => '8888-1234'],
            ['tipo' => 'telefono', 'valor' => '2222-3333'],
        ]])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles', fn (array $d) => ($d['contactos.1.tipo'][0] ?? '') === 'Ya agregaste ese tipo de contacto: va uno de cada tipo. Para un segundo número usá WhatsApp.');
    }

    public function test_hallazgos_34_y_35_politicas_con_zonas_de_m5_y_una_por_tipo(): void
    {
        $id = $this->comoEncargadoDe();
        $envio = fn (string $zona) => ['tipo' => 'envio', 'contenido' => 'Enviamos a todo el país en 2 a 5 días.',
            'configuracion_envio' => [['zona' => $zona, 'costo' => 2500, 'moneda' => 'CRC']]];

        $this->putJson("/api/m2/v1/comercios/{$id}/micrositio/politicas", ['politicas' => [$envio('Cañas')]])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles', fn (array $d) => ($d['politicas.0.configuracion_envio.0.zona'][0] ?? '') === 'Elegí la zona: GAM o Resto del país.');

        $this->putJson("/api/m2/v1/comercios/{$id}/micrositio/politicas", ['politicas' => [$envio('GAM'), $envio('Resto del país')]])
            ->assertStatus(422)
            ->assertJsonPath('error.detalles', fn (array $d) => ($d['politicas.1.tipo'][0] ?? '') === 'Ya agregaste una política de ese tipo: va una de cada tipo.');

        $this->putJson("/api/m2/v1/comercios/{$id}/micrositio/politicas", ['politicas' => [
            ['tipo' => 'devolucion', 'contenido' => 'Corta'],
        ]])->assertStatus(422)->assertJsonPath('error.detalles', fn (array $d) => ($d['politicas.0.contenido'][0] ?? '') === 'Explicá la política con al menos 10 caracteres.');

        $this->putJson("/api/m2/v1/comercios/{$id}/micrositio/politicas", ['politicas' => [$envio('GAM')]])->assertOk();
        $this->assertSame(2500.0, (float) app(ComerciosService::class)->obtenerPoliticasEnvio($id)['tarifas'][0]['costo']);
    }

    // --- Hallazgos 37 y 38: destacados ---

    public function test_hallazgos_37_y_38_rechazo_con_motivo_extension_sin_superponer_y_retiro(): void
    {
        $this->mock(NucleoServiceInterface::class)->shouldReceive('registrarAuditoria');
        $this->travelTo(now()->setDate(2026, 11, 22)->startOfDay());
        $comercio = $this->crearComercio();
        $servicio = app(ComerciosService::class);
        $this->actingAs($this->usuario('encargado_plataforma'), 'sanctum');

        // Destacado de la plataforma: del 22 al 28.
        $servicio->solicitarPublicidad($comercio->id_comercio, 7, true);

        // El encargado pide otro mientras está vigente: al aprobarlo empieza el 29, no hoy.
        $pedida = $servicio->solicitarPublicidad($comercio->id_comercio, 7, false);
        $this->postJson("/api/m2/v1/publicidad/{$pedida->id_publicidad_comercio}", ['accion' => 'rechazar'])
            ->assertStatus(422)->assertJsonPath('error.detalles.motivo.0', 'Escribí el motivo: el comercio lo va a ver en su historial.');
        $this->postJson("/api/m2/v1/publicidad/{$pedida->id_publicidad_comercio}", ['accion' => 'aprobar'])->assertOk();
        $extendida = PublicidadComercio::query()->findOrFail($pedida->id_publicidad_comercio);
        $this->assertSame('2026-11-29', $extendida->inicio->toDateString());
        $this->assertSame('2026-12-05', $extendida->fin->toDateString());

        // Destacados vigentes o programados, para retirarlos.
        $vigentes = $this->getJson('/api/m2/v1/publicidad/vigentes')->assertOk()->json('publicidades');
        $this->assertCount(2, $vigentes);
        $this->assertSame(7, $vigentes[0]['dias_restantes']);

        foreach ($vigentes as $p) {
            $this->postJson("/api/m2/v1/publicidad/{$p['id_publicidad_comercio']}", ['accion' => 'retirar', 'motivo' => 'Publicó contenido indebido.'])->assertOk();
        }
        $historial = $servicio->listarPublicidadComercio($comercio->id_comercio);
        $this->assertFalse($historial['destacado']);
        $this->assertTrue($historial['publicidades'][0]['retirada']);
        $this->assertSame('Publicó contenido indebido.', $historial['publicidades'][0]['motivo']);
        $this->assertSame([], $servicio->listarComerciosHabilitados(1, 20, true)['comercios']);
    }

    public function test_normaliza_contactos_de_cada_tipo(): void
    {
        $this->assertSame(['2222-3333', null], ContactoComercio::normalizar('telefono', '+506 2222-3333'));
        $this->assertSame(['https://tiktok.com/@soda', null], ContactoComercio::normalizar('tiktok', 'soda'));
        $this->assertSame(['https://facebook.com/soda', null], ContactoComercio::normalizar('facebook', 'm.facebook.com/soda/'));
        $this->assertSame(['https://www.soda.cr', null], ContactoComercio::normalizar('sitio_web', 'www.soda.cr'));
        $this->assertNull(ContactoComercio::normalizar('correo', 'sin-arroba')[0]);
        $this->assertNull(ContactoComercio::normalizar('instagram', 'instagram.com')[0]);
    }
}
