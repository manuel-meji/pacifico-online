<?php

use App\Http\Controllers\M2_Comercios\ConsultasComerciosController as Consultas;
use App\Http\Controllers\M2_Comercios\MicrositiosController as Micrositios;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M2 — API del contrato OpenAPI v1.2.1 (contracts/m2-comercios/v1/openapi.yaml)
|--------------------------------------------------------------------------
| Prefijo /api/m2/v1, grupo "api" (sin CSRF). Las carga
| App\Providers\M2ComerciosServiceProvider. Las rutas web del módulo siguen en
| routes/modules/m2_comercios.php (/m2).
*/

Route::prefix('api/m2/v1')
    ->middleware('api')
    ->name('api.m2.v1.')
    ->group(function (): void {
        // Públicas: sin sesión.
        Route::get('comercios/publico', [Consultas::class, 'listar'])->name('comercios.publico');
        Route::post('comercios/publico/lote', [Consultas::class, 'resumenLote'])->name('comercios.lote');
        Route::get('comercios/{idComercio}/publico', [Consultas::class, 'publico'])
            ->whereNumber('idComercio')->name('comercios.micrositio');

        // Con sesión del núcleo (x-autorizacion en ComercioPolicy).
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('comercios/{idComercio}/estado', [Consultas::class, 'estado'])
                ->whereNumber('idComercio')->name('comercios.estado');
            Route::get('comercios/{idComercio}/sedes', [Consultas::class, 'sedes'])
                ->whereNumber('idComercio')->name('comercios.sedes');
            Route::get('comercios/{idComercio}/politicas-envio', [Consultas::class, 'politicasEnvio'])
                ->whereNumber('idComercio')->name('comercios.politicas_envio');
            Route::get('comercios/{idComercio}/datos-fiscales', [Consultas::class, 'datosFiscales'])
                ->whereNumber('idComercio')->name('comercios.datos_fiscales');
            Route::get('subpedidos/{idSubpedido}/comision', [Consultas::class, 'comisionSubpedido'])
                ->whereNumber('idSubpedido')->name('subpedidos.comision');
                // --- CU-0001 a CU-0007: edición del micrositio
            Route::get('comercios/{idComercio}/micrositio', [Micrositios::class, 'consultar'])
                ->whereNumber('idComercio')->name('comercios.micrositio.editar');
            Route::post('comercios/{idComercio}/micrositio/logotipo', [Micrositios::class, 'guardarLogotipo'])
                ->whereNumber('idComercio')->name('comercios.micrositio.logotipo');
            Route::put('comercios/{idComercio}/micrositio/galeria', [Micrositios::class, 'gestionarGaleria'])
                ->whereNumber('idComercio')->name('comercios.micrositio.galeria');
            Route::put('comercios/{idComercio}/micrositio', [Micrositios::class, 'actualizarInformacion'])
                ->whereNumber('idComercio') ->name('comercios.micrositio.actualizar');
            Route::put('comercios/{idComercio}/micrositio/banners', [Micrositios::class, 'gestionarBanners'])
                ->whereNumber('idComercio')->name('comercios.micrositio.banners');
            Route::put('comercios/{idComercio}/micrositio/horarios', [Micrositios::class, 'guardarHorarios'])
                ->whereNumber('idComercio')->name('comercios.micrositio.horarios');
            Route::put('comercios/{idComercio}/micrositio/contactos', [Micrositios::class, 'guardarContactos'])
                ->whereNumber('idComercio')->name('comercios.micrositio.contactos');
            Route::put('comercios/{idComercio}/micrositio/politicas', [Micrositios::class, 'guardarPoliticas'])
                ->whereNumber('idComercio')->name('comercios.micrositio.politicas');
        });
    });
