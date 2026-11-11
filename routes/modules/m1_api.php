<?php

use App\Http\Controllers\M1_Portal\BusquedaController;
use App\Http\Controllers\M1_Portal\ClienteController;
use App\Http\Controllers\M1_Portal\ComercioController;
use App\Http\Controllers\M1_Portal\DestacadosController;
use App\Http\Controllers\M1_Portal\DireccionClienteController;
use App\Http\Controllers\M1_Portal\FichaProductoController;
use App\Http\Controllers\M1_Portal\NoticiasController;
use App\Http\Controllers\M1_Portal\PortadaController;
use App\Http\Controllers\M1_Portal\PromocionesController;
use App\Http\Middleware\EstablecerCliente;
use App\Http\Middleware\EstablecerComercio;
use App\Http\Middleware\RequiereRol;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| M1 - Portal Público y Búsqueda: API del contrato OpenAPI v1.2.0
|--------------------------------------------------------------------------
| Prefijo: /api/m1/v1
| Nombre de ruta: api.m1.v1.*
| Subgrupo 1 (SG1).
|
| Aislamiento del núcleo (tarea 23 del PC4, RN-12, ADR-006): EstablecerCliente
| fija ContextoCliente con el cliente de la sesión, usando ResolvedorCliente de
| M1. En la búsqueda y la ficha la sesión es opcional (security: [] en el
| contrato); si hay sesión de cliente, la búsqueda se registra con su
| id_cliente.
*/

Route::middleware(EstablecerCliente::class)->group(function (): void {
    Route::get('/productos/busqueda', [BusquedaController::class, 'buscar'])->name('productos.busqueda');

    // Página principal: comercios y productos destacados. Pública (security: []).
    Route::get('/destacados', [PortadaController::class, 'listar'])->name('destacados');

    Route::get('/productos/{id_producto}', [FichaProductoController::class, 'mostrar'])
        ->where('id_producto', '[1-9][0-9]*')
        ->name('productos.ficha');

    // Página de comercios y CU-009 Ver micrositio. Públicas (security: []).
    Route::get('/comercios', [ComercioController::class, 'listar'])->name('comercios');

    Route::get('/comercios/{id_comercio}/micrositio', [ComercioController::class, 'micrositio'])
        ->where('id_comercio', '[1-9][0-9]*')
        ->name('comercios.micrositio');
});

Route::get('/categorias', [BusquedaController::class, 'listarCategorias'])->name('categorias');

/*
| Productos destacados: solicitud, aprobación y cobro (contrato 1.4.0, SC-08).
| - Tarifas: consulta pública.
| - Comercio: rol encargado_comercio; EstablecerComercio fija el comercio de la
|   sesión (RN-12), que nunca se toma del cuerpo de la petición.
| - Administración: rol encargado_plataforma; EstablecerComercio le concede el
|   acceso auditado del núcleo.
*/
Route::get('/destacados/tarifas', [DestacadosController::class, 'tarifas'])->name('destacados.tarifas');

// Promociones vigentes de la sección del portal (contrato 1.4.0, SC-08). Pública.
Route::get('/promociones', [PromocionesController::class, 'vigentes'])->name('promociones');

// Noticias publicadas de la plataforma (contrato 1.4.0, SC-08). Públicas.
Route::get('/noticias', [NoticiasController::class, 'listar'])->name('noticias');

Route::get('/noticias/{slug}', [NoticiasController::class, 'mostrar'])
    ->where('slug', '[a-z0-9]+(-[a-z0-9]+)*')
    ->name('noticias.mostrar');

Route::middleware(['auth:sanctum', EstablecerComercio::class, RequiereRol::class.':encargado_comercio'])
    ->prefix('comercio')
    ->name('comercio.')
    ->group(function (): void {
        Route::post('/destacados/solicitudes', [DestacadosController::class, 'solicitar'])->name('destacados.solicitar');
        Route::get('/destacados/solicitudes', [DestacadosController::class, 'solicitudesDelComercio'])->name('destacados.solicitudes');

        // Promociones del comercio (contrato 1.4.0, SC-08).
        Route::post('/promociones', [PromocionesController::class, 'crear'])->name('promociones.crear');
        Route::get('/promociones', [PromocionesController::class, 'delComercio'])->name('promociones.listar');

        Route::put('/promociones/{id_promocion}', [PromocionesController::class, 'actualizar'])
            ->where('id_promocion', '[1-9][0-9]*')
            ->name('promociones.actualizar');

        Route::post('/promociones/{id_promocion}/desactivar', [PromocionesController::class, 'desactivar'])
            ->where('id_promocion', '[1-9][0-9]*')
            ->name('promociones.desactivar');
    });

Route::middleware(['auth:sanctum', EstablecerComercio::class, RequiereRol::class.':encargado_plataforma'])
    ->prefix('administracion')
    ->name('administracion.')
    ->group(function (): void {
        Route::get('/destacados/solicitudes', [DestacadosController::class, 'solicitudes'])->name('destacados.solicitudes');

        Route::post('/destacados/solicitudes/{id_solicitud_destacado}/aprobar', [DestacadosController::class, 'aprobar'])
            ->where('id_solicitud_destacado', '[1-9][0-9]*')
            ->name('destacados.aprobar');

        Route::post('/destacados/solicitudes/{id_solicitud_destacado}/rechazar', [DestacadosController::class, 'rechazar'])
            ->where('id_solicitud_destacado', '[1-9][0-9]*')
            ->name('destacados.rechazar');

        Route::post('/destacados', [DestacadosController::class, 'destacarPorPlataforma'])->name('destacados.crear');

        Route::put('/destacados/tarifas/{periodo_dias}', [DestacadosController::class, 'definirTarifa'])
            ->where('periodo_dias', '[0-9]+')
            ->name('destacados.tarifas.definir');

        // Sección de promociones del portal: orden y ocultar por políticas (contrato 1.4.0, SC-08).
        Route::get('/promociones', [PromocionesController::class, 'administracion'])->name('promociones.listar');

        Route::put('/promociones/{id_promocion}/orden', [PromocionesController::class, 'ordenar'])
            ->where('id_promocion', '[1-9][0-9]*')
            ->name('promociones.ordenar');

        Route::post('/promociones/{id_promocion}/ocultar', [PromocionesController::class, 'ocultar'])
            ->where('id_promocion', '[1-9][0-9]*')
            ->name('promociones.ocultar');

        Route::post('/promociones/{id_promocion}/mostrar', [PromocionesController::class, 'mostrar'])
            ->where('id_promocion', '[1-9][0-9]*')
            ->name('promociones.mostrar');

        // Noticias: solo la administración las publica (contrato 1.4.0, SC-08).
        Route::post('/noticias', [NoticiasController::class, 'crear'])->name('noticias.crear');
        Route::get('/noticias', [NoticiasController::class, 'administracion'])->name('noticias.listar');

        Route::put('/noticias/{id_noticia}', [NoticiasController::class, 'actualizar'])
            ->where('id_noticia', '[1-9][0-9]*')
            ->name('noticias.actualizar');

        Route::post('/noticias/{id_noticia}/publicar', [NoticiasController::class, 'publicar'])
            ->where('id_noticia', '[1-9][0-9]*')
            ->name('noticias.publicar');

        Route::post('/noticias/{id_noticia}/archivar', [NoticiasController::class, 'archivar'])
            ->where('id_noticia', '[1-9][0-9]*')
            ->name('noticias.archivar');
    });

// CU-010 Registrar cliente. Pública (autorregistro); máximo 10 intentos por
// minuto por IP para frenar registros masivos y pruebas de correos.
Route::post('/clientes', [ClienteController::class, 'registrar'])
    ->middleware('throttle:10,1')
    ->name('clientes.registrar');

// Datos de la persona compradora. Requieren la sesión del núcleo, que fija el
// ámbito de cliente; cada persona solo ve y edita sus propios datos
// (RN-M1-16, ERR-003 en cualquier otro caso).
Route::middleware(['auth:sanctum', EstablecerCliente::class])->group(function (): void {
    Route::get('/clientes/sesion', [ClienteController::class, 'sesion'])->name('clientes.sesion');

    Route::get('/clientes/{id_cliente}', [ClienteController::class, 'mostrar'])
        ->where('id_cliente', '[1-9][0-9]*')
        ->name('clientes.mostrar');

    Route::put('/clientes/{id_cliente}', [ClienteController::class, 'actualizar'])
        ->where('id_cliente', '[1-9][0-9]*')
        ->name('clientes.actualizar');

    // Direcciones de envío del cliente (tarea 17).
    Route::get('/clientes/{id_cliente}/direcciones', [DireccionClienteController::class, 'listar'])
        ->where('id_cliente', '[1-9][0-9]*')
        ->name('clientes.direcciones.listar');

    Route::post('/clientes/{id_cliente}/direcciones', [DireccionClienteController::class, 'agregar'])
        ->where('id_cliente', '[1-9][0-9]*')
        ->name('clientes.direcciones.agregar');

    Route::put('/clientes/{id_cliente}/direcciones/{id_direccion_cliente}', [DireccionClienteController::class, 'actualizar'])
        ->where(['id_cliente' => '[1-9][0-9]*', 'id_direccion_cliente' => '[1-9][0-9]*'])
        ->name('clientes.direcciones.actualizar');
});
