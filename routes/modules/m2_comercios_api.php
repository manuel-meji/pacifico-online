<?php

use App\Http\Controllers\M2_Comercios\ConsultasComerciosController as Consultas;
use App\Http\Controllers\M2_Comercios\SolicitudesController as Solicitudes;
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
        });

        // --- CU-0012 a CU-0016 y CU-0022: esqueleto de solicitudes (Jorge) ---
        Route::post('solicitudes', [Solicitudes::class, 'registrar'])
            ->middleware('throttle:10,1')->name('solicitudes.registrar');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::put('solicitudes/{idSolicitud}', [Solicitudes::class, 'corregir'])
                ->whereNumber('idSolicitud')->name('solicitudes.corregir');
            Route::get('solicitudes', [Solicitudes::class, 'listar'])->name('solicitudes.listar');
            Route::get('solicitudes/{idSolicitud}', [Solicitudes::class, 'consultar'])
                ->whereNumber('idSolicitud')->name('solicitudes.consultar');
            Route::post('solicitudes/{idSolicitud}/aprobar', [Solicitudes::class, 'aprobar'])
                ->whereNumber('idSolicitud')->name('solicitudes.aprobar');
            Route::post('solicitudes/{idSolicitud}/rechazar', [Solicitudes::class, 'rechazar'])
                ->whereNumber('idSolicitud')->name('solicitudes.rechazar');
            Route::post('solicitudes/{idSolicitud}/documentos/{idDocumento}/evaluar', [Solicitudes::class, 'evaluarDocumento'])
                ->whereNumber(['idSolicitud', 'idDocumento'])->name('solicitudes.documentos.evaluar');
        });
    });
