<?php

namespace App\Services\M5_Pedidos;

use App\Contracts\M1_Portal\ComercialPortalContract;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M5_Pedidos\CarritoServiceInterface;
use App\Contracts\M5_Pedidos\GestionPedidosServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Events\M5_Pedidos\PedidoPagado;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M1_Portal\DireccionCliente;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoLinea;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PedidosService implements CarritoServiceInterface, GestionPedidosServiceInterface, PedidosServiceInterface, SubpedidosServiceInterface
{
    public function __construct(
        protected ?ComerciosServiceInterface $comerciosService = null,
        protected ?CatalogoServiceInterface $catalogoService = null,
        protected ?InventarioServiceInterface $inventarioService = null,
        protected ?PagosServiceInterface $pagosService = null,
        protected ?PortalServiceInterface $portalService = null,
        protected ?PortalPublicoContract $portalContract = null,
    ) {}

    // =========================================================================
    // B1: Desglose unificado del carrito por comercio e ítem
    // =========================================================================

    /**
     * Calcula el desglose completo de un carrito agrupado por comercio:
     * subtotal por línea, IVA por línea (13% o tasa de M3, nunca sobre envío),
     * costo de envío por comercio, total y estado de suspensión del comercio.
     *
     * Desde contrato M1 v1.4.0 (SC-08): consulta precios vigentes con promoción
     * en M1 mediante obtenerPreciosVigentes(array $idsVariacion).
     *
     * @return array{
     *     items: list<array<string, mixed>>,
     *     grupos_comercio: array<int, array<string, mixed>>,
     *     resumen: array{subtotal: float, impuesto: float, costo_envio: float, total: float},
     *     hay_comercio_suspendido: bool
     * }
     */
    public function calcularDesgloseCarrito(
        ?Carrito $carrito = null,
        ?CatalogoServiceInterface $catalogoOverride = null,
        ?ComerciosServiceInterface $comerciosOverride = null,
        ?object $portalOverride = null,
        string|array|null $zonaEntrega = null,
        ?int $idDireccionCliente = null,
    ): array {
        $catalogo = $catalogoOverride ?? $this->catalogoService ?? (app()->bound(CatalogoServiceInterface::class) ? app(CatalogoServiceInterface::class) : null);
        $comercios = $comerciosOverride ?? $this->comerciosService ?? (app()->bound(ComerciosServiceInterface::class) ? app(ComerciosServiceInterface::class) : null);
        $portal = $portalOverride ?? $this->resolverPortalM1();
        $zona = $this->resolverZonaEntrega(
            $zonaEntrega,
            $idDireccionCliente,
            $carrito?->id_cliente,
            $portal
        );

        if (! $carrito) {
            return [
                'items' => [],
                'grupos_comercio' => [],
                'resumen' => [
                    'subtotal' => 0.0,
                    'impuesto' => 0.0,
                    'costo_envio' => 0.0,
                    'total' => 0.0,
                ],
                'hay_comercio_suspendido' => false,
            ];
        }

        $carrito->loadMissing('items');
        $grupos = [];
        $itemsEnriquecidos = [];
        $cacheComercios = [];
        $cacheDetallesProducto = [];
        $hayComercioSuspendido = false;

        $idsVariacion = $carrito->items
            ->pluck('id_variacion')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $preciosVigentesM1 = $this->consultarPreciosVigentesM1($portal, $idsVariacion);

        foreach ($carrito->items as $item) {
            $idVariacionItem = (int) $item->id_variacion;
            $variacion = null;
            if ($catalogo) {
                try {
                    $variacion = $catalogo->obtenerVariacion($idVariacionItem);
                } catch (\Throwable) {
                    $variacion = null;
                }
            }

            $comercioId = (int) ($variacion['id_comercio'] ?? $variacion['producto']['id_comercio'] ?? 1);
            $precioCatalogo = (float) ($variacion['precio'] ?? 10000.00);
            $tarifaIva = (float) ($variacion['tarifa_impuesto'] ?? 13.00);
            $nombreProducto = (string) ($variacion['nombre'] ?? $variacion['producto']['nombre'] ?? 'Producto #'.$idVariacionItem);
            $sku = (string) ($variacion['sku'] ?? 'SKU-VAR-'.$idVariacionItem);
            $imagen = $this->resolverImagenVariacion($variacion, $idVariacionItem, $catalogo, $cacheDetallesProducto);

            // Precio vigente desde M1 (Contrato M1 v1.4.0 / SC-08)
            $infoPromocion = $preciosVigentesM1[$idVariacionItem] ?? null;
            $precioBase = isset($infoPromocion['precio_base']) ? (float) $infoPromocion['precio_base'] : $precioCatalogo;
            $precio = isset($infoPromocion['precio_final']) ? (float) $infoPromocion['precio_final'] : $precioCatalogo;
            $idPromocion = isset($infoPromocion['id_promocion']) && $infoPromocion['id_promocion'] !== null
                ? (int) $infoPromocion['id_promocion']
                : null;
            $finPromocion = $infoPromocion['fin_promocion'] ?? $infoPromocion['fecha_fin'] ?? $infoPromocion['promocion_fin'] ?? null;
            $descuentoUnitario = round(max(0.0, $precioBase - $precio), 2);
            $descuentoLinea = round($descuentoUnitario * (int) $item->cantidad, 2);

            if (! isset($cacheComercios[$comercioId])) {
                $nombreComercio = 'Comercio #'.$comercioId;
                $costoEnvioComercio = 0.0;
                $suspendido = false;

                if ($comercios) {
                    try {
                        if (method_exists($comercios, 'obtenerComercioActivo')) {
                            $infoEstado = $comercios->obtenerComercioActivo($comercioId);
                            if (isset($infoEstado['activo']) && ! $infoEstado['activo']) {
                                $suspendido = true;
                            }
                            if (isset($infoEstado['estado']) && strtolower((string) $infoEstado['estado']) !== 'activo') {
                                $suspendido = true;
                            }
                        }

                        if (method_exists($comercios, 'obtenerPoliticasEnvio')) {
                            try {
                                $politicas = $comercios->obtenerPoliticasEnvio($comercioId);
                                if (! empty($politicas['tarifas']) && is_array($politicas['tarifas'])) {
                                    $costoEnvioComercio = $this->seleccionarTarifaPorZona($politicas['tarifas'], $zona);
                                }
                            } catch (\Throwable) {
                                // Continuar
                            }
                        }

                        if (method_exists($comercios, 'obtenerResumenComerciosLote')) {
                            $lote = $comercios->obtenerResumenComerciosLote([$comercioId]);
                            $comercioData = $lote['comercios'][0] ?? null;
                            if ($comercioData) {
                                $nombreComercio = (string) ($comercioData['nombre_comercial'] ?? $nombreComercio);
                                if (isset($comercioData['estado']) && strtolower((string) $comercioData['estado']) !== 'activo') {
                                    $suspendido = true;
                                }
                            } else {
                                $suspendido = true;
                            }
                        }

                        if (method_exists($comercios, 'obtenerMicrositio')) {
                            $infoComercio = $comercios->obtenerMicrositio($comercioId);
                            $nombreComercio = (string) ($infoComercio['nombre_comercial'] ?? $infoComercio['nombre'] ?? $nombreComercio);
                            if ($costoEnvioComercio <= 0.0) {
                                $costoEnvioComercio = (float) ($infoComercio['costo_envio'] ?? $infoComercio['tarifa_envio'] ?? 0.0);
                            }
                            if (isset($infoComercio['estado']) && strtolower((string) $infoComercio['estado']) !== 'activo') {
                                $suspendido = true;
                            }
                            if (array_key_exists('activo', $infoComercio) && ! $infoComercio['activo']) {
                                $suspendido = true;
                            }
                        }
                    } catch (\Throwable) {
                        $suspendido = true;
                    }
                }

                $cacheComercios[$comercioId] = [
                    'nombre_comercio' => $nombreComercio,
                    'costo_envio' => round($costoEnvioComercio, 2),
                    'comercio_suspendido' => $suspendido,
                ];
            }

            $metaComercio = $cacheComercios[$comercioId];
            if ($metaComercio['comercio_suspendido']) {
                $hayComercioSuspendido = true;
            }

            $subtotalLinea = $item->calcularSubtotal($precio);
            // RN-M5-01 / RN-M5-02: IVA se calcula exclusivamente por línea, nunca sobre el envío
            $impuestoLinea = round($subtotalLinea * ($tarifaIva / 100), 2);

            if (! isset($grupos[$comercioId])) {
                $grupos[$comercioId] = [
                    'id_comercio' => $comercioId,
                    'nombre_comercio' => $metaComercio['nombre_comercio'],
                    'comercio_suspendido' => $metaComercio['comercio_suspendido'],
                    'items' => [],
                    'subtotal' => 0.0,
                    'impuesto' => 0.0,
                    'costo_envio' => $metaComercio['costo_envio'],
                    'envio' => $metaComercio['costo_envio'],
                    'total' => 0.0,
                ];
            }

            $itemData = [
                'id_item_carrito' => (int) $item->id_item_carrito,
                'id_carrito' => (int) $item->id_carrito,
                'id_variacion' => $idVariacionItem,
                'cantidad' => (int) $item->cantidad,
                'id_reserva' => $item->id_reserva ? (string) $item->id_reserva : null,
                'nombre' => $nombreProducto,
                'sku' => $sku,
                'imagen' => $imagen,
                'precio_base' => $precioBase,
                'precio_final' => $precio,
                'precio' => $precio,
                'precio_unitario' => $precio,
                'descuento_unitario' => $descuentoUnitario,
                'descuento' => $descuentoLinea,
                'id_promocion' => $idPromocion,
                'fin_promocion' => $finPromocion,
                'tarifa_impuesto' => $tarifaIva,
                'id_comercio' => $comercioId,
                'nombre_comercio' => $metaComercio['nombre_comercio'],
                'comercio_suspendido' => $metaComercio['comercio_suspendido'],
                'subtotal' => $subtotalLinea,
                'impuesto' => $impuestoLinea,
                'iva' => $impuestoLinea,
                'created_at' => $item->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'updated_at' => $item->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ];

            $itemsEnriquecidos[] = $itemData;
            $grupos[$comercioId]['items'][] = $itemData;
            $grupos[$comercioId]['subtotal'] = round($grupos[$comercioId]['subtotal'] + $subtotalLinea, 2);
            $grupos[$comercioId]['impuesto'] = round($grupos[$comercioId]['impuesto'] + $impuestoLinea, 2);
            $grupos[$comercioId]['total'] = round(
                $grupos[$comercioId]['subtotal'] + $grupos[$comercioId]['impuesto'] + $grupos[$comercioId]['costo_envio'],
                2
            );
        }

        $resumenSubtotal = 0.0;
        $resumenImpuesto = 0.0;
        $resumenEnvio = 0.0;

        foreach ($grupos as $grupo) {
            $resumenSubtotal = round($resumenSubtotal + $grupo['subtotal'], 2);
            $resumenImpuesto = round($resumenImpuesto + $grupo['impuesto'], 2);
            $resumenEnvio = round($resumenEnvio + $grupo['costo_envio'], 2);
        }

        $resumenTotal = round($resumenSubtotal + $resumenImpuesto + $resumenEnvio, 2);

        return [
            'items' => $itemsEnriquecidos,
            'grupos_comercio' => $grupos,
            'resumen' => [
                'subtotal' => $resumenSubtotal,
                'impuesto' => $resumenImpuesto,
                'costo_envio' => $resumenEnvio,
                'total' => $resumenTotal,
            ],
            'hay_comercio_suspendido' => $hayComercioSuspendido,
        ];
    }

    /**
     * Obtiene la URL de imagen de la variación y, cuando el contrato de M3
     * no la incluye en obtenerVariacion, la resuelve mediante obtenerDetalleProducto.
     *
     * @param  array<string, mixed>|null  $variacion
     * @param  array<int, array<string, mixed>|null>  $cacheDetallesProducto
     */
    private function resolverImagenVariacion(
        ?array $variacion,
        int $idVariacion,
        ?CatalogoServiceInterface $catalogo,
        array &$cacheDetallesProducto
    ): string {
        $imagen = (string) ($variacion['imagen'] ?? $variacion['imagen_url'] ?? '');
        if ($imagen !== '' || ! $catalogo) {
            return $imagen;
        }

        $idProducto = (int) ($variacion['id_producto'] ?? $variacion['producto']['id_producto'] ?? 0);
        if ($idProducto <= 0) {
            return '';
        }

        if (! array_key_exists($idProducto, $cacheDetallesProducto)) {
            try {
                $cacheDetallesProducto[$idProducto] = $catalogo->obtenerDetalleProducto($idProducto);
            } catch (\Throwable) {
                $cacheDetallesProducto[$idProducto] = null;
            }
        }

        $detalle = $cacheDetallesProducto[$idProducto];
        $imagenes = is_array($detalle) ? ($detalle['imagenes'] ?? []) : [];
        if (! is_array($imagenes) || $imagenes === []) {
            return '';
        }

        $primeraUrl = '';
        foreach ($imagenes as $img) {
            if (! is_array($img)) {
                continue;
            }

            $url = (string) ($img['imagen_url'] ?? $img['url'] ?? '');
            if ($url === '') {
                continue;
            }

            if ((int) ($img['id_variacion'] ?? 0) === $idVariacion) {
                return $url;
            }

            if ($primeraUrl === '') {
                $primeraUrl = $url;
            }
        }

        return $primeraUrl;
    }

    /**
     * Resuelve la zona de entrega ('GAM' o 'Resto del país') a partir de la zona explícita,
     * los parámetros del request o consultando la dirección del cliente con M1.
     */
    public function resolverZonaEntrega(
        string|array|null $zonaEntrega = null,
        ?int $idDireccionCliente = null,
        ?int $idCliente = null,
        ?object $portal = null
    ): string {
        if (is_array($zonaEntrega)) {
            return $this->determinarZonaPorDireccion($zonaEntrega);
        }

        if (! empty($zonaEntrega)) {
            return $this->normalizarZona((string) $zonaEntrega);
        }

        if (request()->has('zona') && ! empty(request()->input('zona'))) {
            return $this->normalizarZona((string) request()->input('zona'));
        }

        if (request()->has('zona_entrega') && ! empty(request()->input('zona_entrega'))) {
            return $this->normalizarZona((string) request()->input('zona_entrega'));
        }

        $direccionId = $idDireccionCliente ?? (int) request()->input('id_direccion_cliente', 0);
        $clienteId = $idCliente ?? (int) request()->input('id_cliente', 0);

        if ($clienteId > 0 && $portal) {
            try {
                if ($direccionId > 0 && method_exists($portal, 'obtenerDireccionCliente')) {
                    $dir = $portal->obtenerDireccionCliente($clienteId, $direccionId);
                    if (! empty($dir)) {
                        return $this->determinarZonaPorDireccion($dir);
                    }
                }

                if (method_exists($portal, 'listarDireccionesCliente')) {
                    $dirs = $portal->listarDireccionesCliente($clienteId);
                    if (! empty($dirs)) {
                        $dirPrincipal = collect($dirs)->firstWhere('es_principal', true) ?? ($dirs[0] ?? null);
                        if (! empty($dirPrincipal)) {
                            return $this->determinarZonaPorDireccion($dirPrincipal);
                        }
                    }
                }
            } catch (\Throwable) {
                // Continuar a valor por defecto
            }
        }

        if ($direccionId > 0 && class_exists(DireccionCliente::class)) {
            try {
                $dirDb = DireccionCliente::find($direccionId);
                if ($dirDb) {
                    return $this->determinarZonaPorDireccion($dirDb->toArray());
                }
            } catch (\Throwable) {
            }
        }

        return 'GAM';
    }

    /**
     * Determina si una dirección pertenece a la GAM o al Resto del país según provincia y cantón.
     *
     * @param  array<string, mixed>  $direccion
     */
    public function determinarZonaPorDireccion(array $direccion): string
    {
        if (! empty($direccion['zona'])) {
            return $this->normalizarZona((string) $direccion['zona']);
        }

        $provincia = mb_strtolower(trim((string) ($direccion['provincia'] ?? '')));
        $canton = mb_strtolower(trim((string) ($direccion['canton'] ?? '')));

        // Provincias totalmente fuera de la Gran Área Metropolitana
        if (in_array($provincia, ['guanacaste', 'puntarenas', 'limon', 'limón'], true)) {
            return 'Resto del país';
        }

        // Cantones rurales o periféricos fuera de la GAM
        $cantonesFueraGam = [
            'san carlos', 'upala', 'los chiles', 'guatuso', 'san ramon', 'san ramón',
            'perez zeledon', 'pérez zeledón', 'dota', 'tarrazu', 'tarrazú', 'leon cortes', 'león cortés', 'turrubares',
            'sarapiqui', 'sarapiquí',
            'turrialba', 'jimenez', 'jiménez',
        ];

        if (in_array($canton, $cantonesFueraGam, true)) {
            return 'Resto del país';
        }

        if (in_array($provincia, ['san jose', 'san josé', 'heredia', 'cartago', 'alajuela'], true)) {
            return 'GAM';
        }

        return 'GAM';
    }

    /**
     * Normaliza el texto de una zona a las etiquetas canónicas de M2 ('GAM' o 'Resto del país').
     */
    public function normalizarZona(string $zona): string
    {
        $z = mb_strtolower(trim($zona));
        if (str_contains($z, 'gam')) {
            return 'GAM';
        }
        if (str_contains($z, 'resto') || str_contains($z, 'rural') || str_contains($z, 'fuera') || str_contains($z, 'pais') || str_contains($z, 'país')) {
            return 'Resto del país';
        }

        return $zona;
    }

    /**
     * Selecciona la tarifa de envío de M2 que mejor coincide con la zona de entrega solicitada.
     * Si no hay coincidencia, recurre a la primera tarifa configurada.
     *
     * @param  list<array<string, mixed>>  $tarifas
     */
    public function seleccionarTarifaPorZona(array $tarifas, string $zona = 'GAM'): float
    {
        if (empty($tarifas)) {
            return 0.0;
        }

        $zonaNorm = mb_strtolower(trim($zona));

        // 1. Coincidencia exacta o semántica
        foreach ($tarifas as $t) {
            $zonaTarifa = mb_strtolower(trim((string) ($t['zona'] ?? '')));
            if ($zonaTarifa === $zonaNorm) {
                return (float) ($t['costo'] ?? 0.0);
            }
            if ($zonaNorm === 'gam' && str_contains($zonaTarifa, 'gam')) {
                return (float) ($t['costo'] ?? 0.0);
            }
            if ($zonaNorm !== 'gam' && (str_contains($zonaTarifa, 'resto') || str_contains($zonaTarifa, 'país') || str_contains($zonaTarifa, 'pais'))) {
                return (float) ($t['costo'] ?? 0.0);
            }
        }

        // 2. Fallback a la primera tarifa disponible
        return (float) ($tarifas[0]['costo'] ?? 0.0);
    }

    /**
     * Resuelve el servicio o doble de M1 para consultas de precios vigentes.
     */
    private function resolverPortalM1(): ?object
    {
        if ($this->portalContract !== null) {
            return $this->portalContract;
        }

        if ($this->portalService !== null) {
            return $this->portalService;
        }

        if (app()->resolved(PortalPublicoContract::class) && app()->bound(PortalPublicoContract::class)) {
            return app(PortalPublicoContract::class);
        }

        if (app()->resolved(PortalServiceInterface::class) && app()->bound(PortalServiceInterface::class)) {
            return app(PortalServiceInterface::class);
        }

        $modo = config('integraciones.m5.m1.modo');
        $doble = config('integraciones.m5.m1.doble');
        if ($modo === 'doble' && is_string($doble) && class_exists($doble)) {
            return app($doble);
        }

        if (app()->bound(PortalPublicoContract::class)) {
            return app(PortalPublicoContract::class);
        }

        return null;
    }

    /**
     * Consulta a M1 los precios vigentes por lote (contrato M1 v1.4.0 / SC-08).
     * Normaliza tanto respuestas indexadas por id_variacion como listas secuenciales.
     *
     * @param  list<int>  $idsVariacion
     * @return array<int, array<string, mixed>>
     */
    private function consultarPreciosVigentesM1(?object $portal, array $idsVariacion): array
    {
        if (! $portal || $idsVariacion === [] || ! method_exists($portal, 'obtenerPreciosVigentes')) {
            return [];
        }

        try {
            $respuesta = $portal->obtenerPreciosVigentes($idsVariacion);
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($respuesta)) {
            return [];
        }

        $lista = $respuesta['precios'] ?? $respuesta['variaciones'] ?? $respuesta;
        if (! is_array($lista)) {
            return [];
        }

        $mapa = [];
        foreach ($lista as $clave => $datos) {
            if (! is_array($datos)) {
                continue;
            }
            $idVar = isset($datos['id_variacion'])
                ? (int) $datos['id_variacion']
                : (is_numeric($clave) ? (int) $clave : 0);

            if ($idVar > 0) {
                $mapa[$idVar] = $datos;
            }
        }

        return $mapa;
    }

    // =========================================================================
    // CarritoServiceInterface
    // =========================================================================

    public function obtenerCarrito(int $idCliente): array
    {
        if ($this->hasTable('pedidos_carritos')) {
            $carrito = (new Carrito)->obtenerOCrearCarrito($idCliente);
            $carrito->loadMissing('items');
            $desglose = $this->calcularDesgloseCarrito($carrito);

            if ($desglose['resumen']['total'] > 0.0) {
                $carrito->recalcularTotales($desglose['resumen']['total']);
            }

            return [
                'id_carrito' => (int) $carrito->id_carrito,
                'id_cliente' => (int) $carrito->id_cliente,
                'monto_estimado' => (float) ($carrito->monto_estimado ?? $desglose['resumen']['total']),
                'created_at' => $carrito->created_at?->toIso8601String(),
                'updated_at' => $carrito->updated_at?->toIso8601String(),
                'items' => $desglose['items'],
                'grupos_comercio' => array_values($desglose['grupos_comercio']),
                'resumen' => $desglose['resumen'],
            ];
        }

        return [
            'id_carrito' => 1,
            'id_cliente' => $idCliente,
            'monto_estimado' => 0.0,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'items' => [],
            'grupos_comercio' => [],
            'resumen' => [
                'subtotal' => 0.0,
                'impuesto' => 0.0,
                'costo_envio' => 0.0,
                'total' => 0.0,
            ],
        ];
    }

    public function vaciarCarrito(int $idCliente): array
    {
        if ($this->hasTable('pedidos_carritos')) {
            $carrito = Carrito::where('id_cliente', $idCliente)->first();
            if ($carrito) {
                $carrito->vaciar();
            }
        }

        return [
            'mensaje' => 'Carrito vaciado correctamente.',
        ];
    }

    public function agregarItemCarrito(int $idCliente, array $datos): array
    {
        $idVariacion = (int) ($datos['id_variacion'] ?? 1);
        $cantidad = (int) ($datos['cantidad'] ?? 1);
        $idReserva = $datos['id_reserva'] ?? null;

        if ($this->hasTable('pedidos_carritos') && $this->hasTable('items_carrito')) {
            $carrito = (new Carrito)->obtenerOCrearCarrito($idCliente);
            $item = (new ItemCarrito)->crearItemCarrito($carrito->id_carrito, $idVariacion, $cantidad);
            if ($idReserva !== null) {
                $item->id_reserva = $idReserva;
                $item->save();
            }

            return $this->obtenerCarrito($idCliente);
        }

        return [
            'id_carrito' => 1,
            'id_cliente' => $idCliente,
            'monto_estimado' => 15000.0,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'items' => [
                [
                    'id_item_carrito' => 1,
                    'id_carrito' => 1,
                    'id_variacion' => $idVariacion,
                    'cantidad' => $cantidad,
                    'id_reserva' => $idReserva,
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
        ];
    }

    public function actualizarCantidadItemCarrito(int $idItemCarrito, int $cantidad, ?int $idCliente = null): array
    {
        if ($this->hasTable('items_carrito')) {
            $item = ItemCarrito::find($idItemCarrito);
            if ($item) {
                $item->actualizarCantidad($cantidad);
                $carrito = Carrito::find($item->id_carrito);
                if ($carrito) {
                    return $this->obtenerCarrito((int) $carrito->id_cliente);
                }
            }
        }

        return $idCliente !== null ? $this->obtenerCarrito($idCliente) : [
            'id_carrito' => 1,
            'id_cliente' => $idCliente ?? 1,
            'monto_estimado' => 0.0,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'items' => [],
        ];
    }

    public function eliminarItemCarrito(int $idItemCarrito, ?int $idCliente = null): array
    {
        if ($this->hasTable('items_carrito')) {
            $item = ItemCarrito::find($idItemCarrito);
            if ($item) {
                $carritoId = $item->id_carrito;
                $item->delete();
                $carrito = Carrito::find($carritoId);
                if ($carrito) {
                    return $this->obtenerCarrito((int) $carrito->id_cliente);
                }
            }
        }

        return $idCliente !== null ? $this->obtenerCarrito($idCliente) : [
            'id_carrito' => 1,
            'id_cliente' => $idCliente ?? 1,
            'monto_estimado' => 0.0,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'items' => [],
        ];
    }

    // =========================================================================
    // SubpedidosServiceInterface
    // =========================================================================

    public function obtenerSubpedido(int $idSubpedido): array
    {
        if ($this->hasTable('pedidos_subpedidos')) {
            $subpedido = Subpedido::with(['lineas', 'estados', 'cancelaciones'])->find($idSubpedido);
            if ($subpedido) {
                $infoVariaciones = $this->resolverInfoVariaciones(
                    $subpedido->lineas->pluck('id_variacion')->map(fn ($id): int => (int) $id)->all()
                );

                return [
                    'id_subpedido' => $subpedido->id_subpedido,
                    'id_pedido' => $subpedido->id_pedido,
                    'id_comercio' => $subpedido->id_comercio,
                    'subtotal' => (float) $subpedido->subtotal,
                    'costo_envio' => (float) $subpedido->costo_envio,
                    'impuesto' => (float) $subpedido->impuesto,
                    'total' => (float) ($subpedido->total ?? ($subpedido->subtotal + $subpedido->costo_envio + $subpedido->impuesto)),
                    'estado_subpedido' => (string) $subpedido->estado_subpedido,
                    'guia_seguimiento' => $subpedido->guia_seguimiento,
                    'fecha_despacho' => $subpedido->fecha_despacho?->toIso8601String(),
                    'created_at' => $subpedido->created_at?->toIso8601String(),
                    'updated_at' => $subpedido->updated_at?->toIso8601String(),
                    'lineas' => $subpedido->lineas->map(fn ($l) => $this->formatearLineaConCatalogo($l, $infoVariaciones))->all(),
                    'historial_estados' => $subpedido->estados?->toArray() ?? [],
                    'cancelaciones' => $subpedido->cancelaciones?->toArray() ?? [],
                ];
            }
        }

        return [
            'id_subpedido' => $idSubpedido,
            'id_pedido' => 101,
            'id_comercio' => 2,
            'subtotal' => 15000.00,
            'costo_envio' => 500.00,
            'impuesto' => 1950.00,
            'estado_subpedido' => 'pendiente',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'lineas' => [],
        ];
    }

    public function despacharSubpedido(int $subpedidoId, ?string $guiaTransporte = null): array
    {
        $pedidoId = 101;
        $subtotal = 15000.00;
        $subpedido = null;

        if ($this->hasTable('pedidos_subpedidos')) {
            $subpedido = Subpedido::find($subpedidoId);
            if ($subpedido) {
                $subpedido->despachar($guiaTransporte);
                $pedidoId = (int) $subpedido->id_pedido;
                $subtotal = (float) $subpedido->subtotal;
            }
        }

        // Paso 9 del recorrido transversal: M5 emite SubpedidoDespachado;
        // M2 consume este evento de forma asíncrona para registrar la comisión.
        event(new SubpedidoDespachado($subpedido ?? $subpedidoId, $guiaTransporte));

        return [
            'id_subpedido' => $subpedidoId,
            'id_pedido' => $pedidoId,
            'estado_subpedido' => 'despachado',
            'guia_transporte' => $guiaTransporte,
            'evento' => 'SubpedidoDespachado',
            'consumidores_notificados' => ['M1', 'M2', 'M4', 'M7'],
        ];
    }

    public function confirmarEntregaSubpedido(int $idSubpedido, array $datosEntrega = []): array
    {
        if ($this->hasTable('pedidos_subpedidos')) {
            $subpedido = Subpedido::find($idSubpedido);
            if ($subpedido) {
                $subpedido->entregar();

                return $this->obtenerSubpedido($idSubpedido);
            }
        }

        return [
            'id_subpedido' => $idSubpedido,
            'id_pedido' => 101,
            'id_comercio' => 2,
            'estado_subpedido' => 'entregado',
            'evento' => 'SubpedidoEntregado',
            'fecha_entrega' => now()->toIso8601String(),
        ];
    }

    public function cancelarSubpedidoParcial(int $idSubpedido, array $datosCancelacion = []): array
    {
        $motivo = (string) ($datosCancelacion['motivo'] ?? 'Cancelación parcial solicitada');

        if ($this->hasTable('pedidos_subpedidos')) {
            $subpedido = Subpedido::with(['pedidoMaestro', 'lineas'])->find($idSubpedido);
            if ($subpedido) {
                $estadoPrevio = strtolower((string) $subpedido->estado_subpedido);
                $estabaPagado = ($estadoPrevio === 'pagado');

                $subpedido->cancelar($motivo);

                // Liberar reservas en M4 (RN-M5-10)
                $this->liberarReservasLineas($subpedido->lineas, $motivo);

                // Solicitar reembolso en M6 si ya estaba pagado (RN-M5-10)
                if ($estabaPagado) {
                    $montoReembolso = (float) ($subpedido->total ?? ($subpedido->subtotal + $subpedido->impuesto + $subpedido->costo_envio));
                    $this->solicitarReembolsoM6((int) $subpedido->id_pedido, $montoReembolso, $motivo, (int) $subpedido->id_subpedido);
                }

                // Si todos los subpedidos están cancelados, cancelar el maestro
                $pedidoMaestro = $subpedido->pedidoMaestro()->withoutGlobalScope('cliente')->first();
                if ($pedidoMaestro) {
                    $activos = $pedidoMaestro->subpedidos()->withoutGlobalScope('comercio')->where('estado_subpedido', '!=', 'cancelado')->count();
                    if ($activos === 0 && strtolower((string) $pedidoMaestro->estado_pedido) !== 'cancelado') {
                        $pedidoMaestro->actualizarEstado('cancelado', $motivo);
                    }
                }

                return $this->obtenerSubpedido($idSubpedido);
            }
        }

        return [
            'id_subpedido' => $idSubpedido,
            'estado_subpedido' => 'cancelado',
            'motivo' => $motivo,
            'requiere_reembolso' => $datosCancelacion['requiere_reembolso'] ?? true,
            'evento' => 'SubpedidoCancelado',
        ];
    }

    // =========================================================================
    // PedidosServiceInterface (Maestro y transversales)
    // =========================================================================

    public function listarPedidos(?int $idCliente = null, array $filtros = []): array
    {
        $page = (int) ($filtros['page'] ?? 1);
        $perPage = (int) ($filtros['per_page'] ?? 15);

        if ($this->hasTable('pedidos_maestros')) {
            $query = PedidoMaestro::with('subpedidos.lineas');
            if ($idCliente !== null) {
                $query->where('id_cliente', $idCliente);
            }
            if (! empty($filtros['estado'])) {
                $query->where('estado_pedido', $filtros['estado']);
            }

            $total = $query->count();
            $pedidos = $query->orderByDesc('fecha_creacion')->forPage($page, $perPage)->get();

            return [
                'pagina' => $page,
                'por_pagina' => $perPage,
                'total_resultados' => $total,
                'pedidos' => $pedidos->map(fn ($p) => $this->obtenerPedido((int) $p->id_pedido))->all(),
            ];
        }

        return [
            'pagina' => $page,
            'por_pagina' => $perPage,
            'total_resultados' => 0,
            'pedidos' => [],
        ];
    }

    /**
     * B3 + D4: Crea el PedidoMaestro, un Subpedido por cada comercio involucrado
     * y sus PedidoLineas dentro de una transacción ACID (DB::transaction),
     * garantizando la cuadratura contable del CHECK y vaciando el carrito al final.
     *
     * @param  array<string, mixed>  $datosCheckout
     * @return array<string, mixed>
     */
    public function crearPedidoCheckout(array $datosCheckout): array
    {
        $idCliente = (int) ($datosCheckout['id_cliente'] ?? 1);
        $idDireccion = isset($datosCheckout['id_direccion_cliente']) ? (int) $datosCheckout['id_direccion_cliente'] : null;
        $codigoCupon = trim((string) ($datosCheckout['cupon_descuento'] ?? ''));
        $cupon = $codigoCupon !== '' ? strtoupper($codigoCupon) : null;
        $tarjetaRegalo = $datosCheckout['tarjeta_regalo'] ?? null;
        $idReservaDefault = $datosCheckout['id_reserva'] ?? null;
        $reservasPorVariacion = is_array($datosCheckout['reservas_por_variacion'] ?? null)
            ? $datosCheckout['reservas_por_variacion']
            : [];

        if ($this->hasTable('pedidos_maestros')) {
            $carrito = $datosCheckout['carrito'] ?? null;
            if (! $carrito instanceof Carrito && $this->hasTable('pedidos_carritos')) {
                $carrito = Carrito::with('items')->where('id_cliente', $idCliente)->first();
            }

            $grupos = $datosCheckout['grupos_comercio'] ?? null;
            $resumen = $datosCheckout['resumen'] ?? null;

            if ($grupos === null && $carrito instanceof Carrito && $carrito->items->isNotEmpty()) {
                $desglose = $this->calcularDesgloseCarrito($carrito);
                $grupos = $desglose['grupos_comercio'];
                $resumen = $desglose['resumen'];
            }

            [$subtotalMaestro, $impuestoMaestro, $envioMaestro] = $this->calcularTotalesCheckout($resumen, $grupos);
            $resultadoCupon = $this->validarCuponCheckout(
                $codigoCupon,
                $idCliente,
                is_array($grupos) ? $grupos : [],
                $subtotalMaestro
            );
            $descuentoMaestro = round((float) ($resultadoCupon['monto_descuento'] ?? 0.0), 2);
            $montoTotal = max(0.0, round($subtotalMaestro + $envioMaestro + $impuestoMaestro - $descuentoMaestro, 2));

            $pedidoCreado = DB::transaction(function () use (
                $idCliente,
                $idDireccion,
                $cupon,
                $tarjetaRegalo,
                $idReservaDefault,
                $reservasPorVariacion,
                $carrito,
                $grupos,
                $subtotalMaestro,
                $impuestoMaestro,
                $envioMaestro,
                $descuentoMaestro,
                $montoTotal,
                $resultadoCupon
            ): PedidoMaestro {
                $pedidoMaestro = new PedidoMaestro;
                $idPedido = $pedidoMaestro->crearPedidoMaestro(
                    $montoTotal,
                    $idCliente,
                    $cupon,
                    $tarjetaRegalo,
                    $idDireccion,
                    $subtotalMaestro,
                    $impuestoMaestro,
                    $envioMaestro,
                    $descuentoMaestro,
                    'pendiente'
                );

                $this->registrarUsoCuponSiAplica($resultadoCupon, $idCliente, $idPedido);

                if (is_array($grupos) && ! empty($grupos)) {
                    $subpedidoObj = new Subpedido;
                    foreach ($grupos as $grupo) {
                        $subpedidosCreados = $subpedidoObj->crearSubpedidos([
                            [
                                'id_comercio' => (int) ($grupo['id_comercio'] ?? 1),
                                'subtotal' => (float) ($grupo['subtotal'] ?? 0.0),
                                'costo_envio' => (float) ($grupo['costo_envio'] ?? 0.0),
                                'impuesto' => (float) ($grupo['impuesto'] ?? 0.0),
                            ],
                        ], $idPedido);

                        $subpedido = $subpedidosCreados[0];

                        foreach ($grupo['items'] ?? [] as $itemData) {
                            $idVarLinea = (int) ($itemData['id_variacion'] ?? 0);
                            $lineaReserva = (string) ($itemData['id_reserva'] ?? $reservasPorVariacion[$idVarLinea] ?? $idReservaDefault ?? '');
                            $descuentoLinea = (float) ($itemData['descuento'] ?? 0.0);
                            $idPromocionLinea = isset($itemData['id_promocion']) && $itemData['id_promocion'] !== null
                                ? (int) $itemData['id_promocion']
                                : null;

                            (new PedidoLinea)->crearLinea(
                                $subpedido->id_subpedido,
                                (int) $itemData['id_variacion'],
                                (int) $itemData['cantidad'],
                                (float) $itemData['precio_unitario'],
                                (float) $itemData['tarifa_impuesto'],
                                $lineaReserva !== '' ? $lineaReserva : null,
                                $descuentoLinea,
                                $idPromocionLinea
                            );
                        }
                    }
                }

                if ($carrito instanceof Carrito) {
                    $carrito->vaciar();
                }

                return $pedidoMaestro->fresh(['subpedidos.lineas', 'estados', 'cancelaciones']) ?? $pedidoMaestro;
            });

            return $this->obtenerPedido((int) $pedidoCreado->id_pedido);
        }

        return [
            'id_pedido' => rand(100, 999),
            'id_cliente' => $idCliente,
            'id_direccion_cliente' => $idDireccion,
            'fecha_creacion' => now()->toIso8601String(),
            'monto_total' => 16950.00,
            'estado_pedido' => 'pendiente',
            'subpedidos' => [],
        ];
    }

    public function crearPedido(array $datosCompra): array
    {
        // Paso 3: Confirmación de carrito y creación de pedido (recorrido transversal)
        return [
            'pedido_id' => rand(100, 999),
            'estado' => 'PENDIENTE_PAGO',
            'subtotal' => 15000.00,
            'impuesto' => 1950.00,
            'total' => 16950.00,
        ];
    }

    public function obtenerPedido(int $idPedido): array
    {
        if ($this->hasTable('pedidos_maestros')) {
            $pedido = PedidoMaestro::with(['subpedidos.lineas', 'estados', 'cancelaciones'])->find($idPedido);
            if (! $pedido) {
                throw ErrorNucleoException::recursoNoEncontrado("Pedido #{$idPedido} no encontrado.");
            }

            $respuesta = [
                'id_pedido' => $pedido->id_pedido,
                'id_cliente' => $pedido->id_cliente,
                'id_direccion_cliente' => $pedido->id_direccion_cliente,
                'fecha_creacion' => $pedido->fecha_creacion ? $pedido->fecha_creacion->toIso8601String() : now()->toIso8601String(),
                'subtotal' => (float) $pedido->subtotal,
                'costo_envio' => (float) $pedido->costo_envio,
                'impuesto' => (float) $pedido->impuesto,
                'descuento' => (float) $pedido->descuento,
                'monto_total' => (float) $pedido->monto_total,
                'estado_pedido' => (string) $pedido->estado_pedido,
                'estado' => strtoupper((string) $pedido->estado_pedido),
                'cupon_descuento' => $pedido->cupon_descuento,
                'tarjeta_regalo' => $pedido->tarjeta_regalo,
                'subpedidos' => (function () use ($pedido): array {
                    $infoVariaciones = $this->resolverInfoVariaciones(
                        $pedido->subpedidos
                            ->flatMap(fn ($s) => $s->lineas->pluck('id_variacion'))
                            ->map(fn ($id): int => (int) $id)
                            ->all()
                    );

                    return $pedido->subpedidos->map(fn ($sub) => [
                        'id_subpedido' => $sub->id_subpedido,
                        'id_pedido' => $sub->id_pedido,
                        'id_comercio' => $sub->id_comercio,
                        'subtotal' => (float) $sub->subtotal,
                        'costo_envio' => (float) $sub->costo_envio,
                        'impuesto' => (float) $sub->impuesto,
                        'impuestos' => (float) $sub->impuesto,
                        'total' => (float) ($sub->total ?? ($sub->subtotal + $sub->costo_envio + $sub->impuesto)),
                        'estado_subpedido' => (string) $sub->estado_subpedido,
                        'guia_seguimiento' => $sub->guia_seguimiento,
                        'fecha_despacho' => $sub->fecha_despacho?->toIso8601String(),
                        'lineas' => $sub->lineas->map(fn ($l) => $this->formatearLineaConCatalogo($l, $infoVariaciones))->all(),
                    ])->all();
                })(),
                'historial_estados' => $pedido->estados?->toArray() ?? [],
                'cancelaciones' => $pedido->cancelaciones?->toArray() ?? [],
            ];

            if (config('integraciones.m5.m6.modo') === 'real') {
                $respuesta['urlPasarelaPago'] = "/m6.html?pedido={$pedido->id_pedido}&monto=".number_format((float) $pedido->monto_total, 2, '.', ''); // justin M6 modificó
            }

            return $respuesta;
        }

        return [
            'id_pedido' => $idPedido,
            'id_cliente' => 101,
            'monto_total' => 25000.00,
            'estado_pedido' => 'confirmado',
            'estado' => 'CONFIRMADO',
            'fecha_creacion' => now()->toIso8601String(),
            'subpedidos' => [
                [
                    'id_subpedido' => 801,
                    'id_comercio' => 2,
                    'subtotal' => 22000.00,
                    'impuestos' => 3000.00,
                    'costo_envio' => 0.00,
                ],
            ],
        ];
    }

    public function cancelarPedidoTotal(int $idPedido, array $datosCancelacion = []): array
    {
        $motivo = (string) ($datosCancelacion['motivo'] ?? 'Cancelación solicitada por el cliente');

        if ($this->hasTable('pedidos_maestros')) {
            $pedido = PedidoMaestro::with('subpedidos.lineas')->find($idPedido);
            if ($pedido) {
                $estadoPrevio = strtolower((string) $pedido->estado_pedido);
                $estabaPagado = ($estadoPrevio === 'pagado') || $pedido->subpedidos->contains(
                    fn ($s) => strtolower((string) $s->estado_subpedido) === 'pagado'
                );

                $pedido->cancelar($motivo);

                // Liberar reservas en M4 por cada línea (RN-M5-10)
                foreach ($pedido->subpedidos as $sub) {
                    $this->liberarReservasLineas($sub->lineas, $motivo);
                }

                // Solicitar reembolso en M6 si ya estaba pagado (RN-M5-10)
                if ($estabaPagado) {
                    $this->solicitarReembolsoM6((int) $pedido->id_pedido, (float) $pedido->monto_total, $motivo);
                }

                return $this->obtenerPedido($idPedido);
            }
        }

        return [
            'id_pedido' => $idPedido,
            'estado_pedido' => 'cancelado',
            'motivo' => $motivo,
            'requiere_reembolso' => $datosCancelacion['requiere_reembolso'] ?? true,
            'evento' => 'PedidoCancelado',
        ];
    }

    public function marcarComoPagado(int $pedidoId, string $comprobantePago): bool
    {
        if ($this->hasTable('pedidos_maestros')) {
            $pedido = PedidoMaestro::with('subpedidos.lineas')->find($pedidoId);
            if (! $pedido) {
                return false;
            }

            // B6: Idempotencia si M6 reintenta sobre un pedido ya pagado
            if (strtolower((string) $pedido->estado_pedido) === 'pagado') {
                return true;
            }

            $motivo = 'Pago confirmado ('.$comprobantePago.')';
            $pedido->actualizarEstado('pagado', $motivo);

            foreach ($pedido->subpedidos as $subpedido) {
                if (strtolower((string) $subpedido->estado_subpedido) !== 'pagado') {
                    $subpedido->cambiarEstado('pagado', $motivo);
                }
                $this->confirmarReservasLineas($subpedido->lineas);
            }

            PedidoPagado::dispatch($pedido->fresh('subpedidos.lineas'));
        }

        return true;
    }

    /**
     * Libera en M4 las reservas asociadas a una colección de líneas de pedido.
     *
     * @param  iterable<PedidoLinea>  $lineas
     */
    public function liberarReservasLineas(iterable $lineas, string $motivo = 'Cancelación de pedido'): void
    {
        $inventario = $this->inventarioService ?? (app()->bound(InventarioServiceInterface::class) ? app(InventarioServiceInterface::class) : null);
        if (! $inventario || ! method_exists($inventario, 'liberarReserva')) {
            return;
        }

        foreach ($lineas as $linea) {
            if (! empty($linea->id_reserva)) {
                try {
                    $inventario->liberarReserva((string) $linea->id_reserva, $motivo);
                } catch (\Throwable) {
                }
            }
        }
    }

    /**
     * Confirma en M4 las reservas asociadas a una colección de líneas de pedido tras el pago.
     *
     * @param  iterable<PedidoLinea>  $lineas
     */
    public function confirmarReservasLineas(iterable $lineas): void
    {
        $inventario = $this->inventarioService ?? (app()->bound(InventarioServiceInterface::class) ? app(InventarioServiceInterface::class) : null);
        if (! $inventario) {
            return;
        }

        foreach ($lineas as $linea) {
            if (! empty($linea->id_reserva)) {
                try {
                    if (method_exists($inventario, 'confirmarReserva')) {
                        // Contrato M4 v1.3.0: confirmarReserva(id_reserva, id_pedido, id_subpedido).
                        $inventario->confirmarReserva(
                            (string) $linea->id_reserva,
                            (int) $linea->subpedido->id_pedido,
                            (int) $linea->id_subpedido,
                        );
                    }
                } catch (\Throwable) {
                }
            }
        }
    }

    /**
     * Solicita el reembolso del monto a M6 cuando se cancela un pedido/subpedido ya pagado.
     */
    public function solicitarReembolsoM6(int $idPedido, float $monto, string $motivo, ?int $idSubpedido = null): void
    {
        $pagos = $this->pagosService ?? (app()->bound(PagosServiceInterface::class) ? app(PagosServiceInterface::class) : null);
        if (! $pagos) {
            return;
        }

        try {
            if (method_exists($pagos, 'solicitarReembolso')) {
                $pagos->solicitarReembolso($idPedido, $monto, $motivo, $idSubpedido);
            } elseif (method_exists($pagos, 'reembolsarPago')) {
                $pagos->reembolsarPago($idPedido, $monto, $motivo);
            }
        } catch (\Throwable $e) {
            Log::error('M5: error al solicitar reembolso en M6.', [
                'id_pedido' => $idPedido,
                'id_subpedido' => $idSubpedido,
                'monto' => $monto,
                'exception' => $e,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $grupos
     * @return array{valido: bool, id_cupon: int|null, monto_descuento: float, mensaje: string|null}
     */
    public function validarCuponCheckout(string $codigoCupon, int $idCliente, array $grupos, float $subtotal): array
    {
        $codigo = trim($codigoCupon);
        if ($codigo === '') {
            return ['valido' => true, 'id_cupon' => null, 'monto_descuento' => 0.0, 'mensaje' => null];
        }

        $comercial = $this->resolverComercialPortalM1();
        if ($comercial === null) {
            return ['valido' => true, 'id_cupon' => null, 'monto_descuento' => 0.0, 'mensaje' => null];
        }

        $idsComercios = [];
        $subtotalesPorComercio = [];
        foreach ($grupos as $grupo) {
            $idComercio = (int) ($grupo['id_comercio'] ?? 0);
            if ($idComercio > 0) {
                $idsComercios[] = $idComercio;
                $subtotalesPorComercio[$idComercio] = round((float) ($grupo['subtotal'] ?? 0.0), 2);
            }
        }

        $resultado = $comercial->validarCupon(
            $codigo,
            $idCliente,
            round($subtotal, 2),
            array_values(array_unique($idsComercios)),
            $subtotalesPorComercio
        );

        if (! ($resultado['valido'] ?? false)) {
            throw ValidationException::withMessages([
                'cupon_descuento' => [$resultado['mensaje'] ?? 'El cupón ingresado no es válido.'],
            ]);
        }

        return [
            'valido' => true,
            'id_cupon' => isset($resultado['id_cupon']) ? (int) $resultado['id_cupon'] : null,
            'monto_descuento' => round((float) ($resultado['monto_descuento'] ?? 0.0), 2),
            'mensaje' => $resultado['mensaje'] ?? null,
        ];
    }

    /**
     * @param  array{id_cupon?: int|null}  $resultadoCupon
     */
    private function registrarUsoCuponSiAplica(array $resultadoCupon, int $idCliente, int $idPedido): void
    {
        $idCupon = $resultadoCupon['id_cupon'] ?? null;
        if (! $idCupon) {
            return;
        }

        $comercial = $this->resolverComercialPortalM1();
        if ($comercial === null) {
            return;
        }

        if (! $comercial->registrarUsoCupon((int) $idCupon, $idCliente, $idPedido)) {
            throw ValidationException::withMessages([
                'cupon_descuento' => ['El cupón ya no se encuentra disponible.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $resumen
     * @param  array<int, array<string, mixed>>|null  $grupos
     * @return array{0: float, 1: float, 2: float}
     */
    private function calcularTotalesCheckout(?array $resumen, ?array $grupos): array
    {
        if (is_array($resumen)) {
            return [
                round((float) ($resumen['subtotal'] ?? 0.0), 2),
                round((float) ($resumen['impuesto'] ?? 0.0), 2),
                round((float) ($resumen['costo_envio'] ?? 0.0), 2),
            ];
        }

        $subtotal = 0.0;
        $impuesto = 0.0;
        $envio = 0.0;

        foreach ($grupos ?? [] as $grupo) {
            $subtotal = round($subtotal + (float) ($grupo['subtotal'] ?? 0.0), 2);
            $impuesto = round($impuesto + (float) ($grupo['impuesto'] ?? 0.0), 2);
            $envio = round($envio + (float) ($grupo['costo_envio'] ?? 0.0), 2);
        }

        return [$subtotal, $impuesto, $envio];
    }

    private function resolverComercialPortalM1(): ?ComercialPortalContract
    {
        if (config('integraciones.m5.m1.modo') !== 'doble' && app()->bound(ComercialPortalContract::class)) {
            return app(ComercialPortalContract::class);
        }

        return null;
    }

    /**
     * Resuelve nombre legible, nombre del producto, SKU y atributos por id_variacion desde M3.
     *
     * @param  list<int>  $idsVariacion
     * @return array<int, array{nombre: string, nombre_producto: string, sku: ?string, atributos: list<array<string, mixed>>}>
     */
    public function resolverInfoVariaciones(array $idsVariacion): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsVariacion), fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $catalogo = $this->catalogoService ?? (app()->bound(CatalogoServiceInterface::class) ? app(CatalogoServiceInterface::class) : null);
        if (! $catalogo) {
            return [];
        }

        $mapa = [];
        foreach ($ids as $idVar) {
            try {
                $variacion = $catalogo->obtenerVariacion($idVar);
            } catch (\Throwable) {
                $variacion = null;
            }

            if (! is_array($variacion)) {
                continue;
            }

            $nombreBase = trim((string) ($variacion['nombre'] ?? $variacion['producto']['nombre'] ?? ''));
            if ($nombreBase === '') {
                continue;
            }

            $atributos = is_array($variacion['atributos'] ?? null) ? array_values($variacion['atributos']) : [];
            $resumenAtributos = $this->formatearAtributosVariacion($atributos);
            $nombreCompleto = $resumenAtributos !== '' ? "{$nombreBase} ({$resumenAtributos})" : $nombreBase;

            $mapa[$idVar] = [
                'nombre' => $nombreCompleto,
                'nombre_producto' => $nombreBase,
                'sku' => isset($variacion['sku']) && $variacion['sku'] !== '' ? (string) $variacion['sku'] : null,
                'atributos' => $atributos,
            ];
        }

        return $mapa;
    }

    /**
     * @param  array<int, array{nombre: string, nombre_producto: string, sku: ?string, atributos: list<array<string, mixed>>}>  $infoVariaciones
     * @return array<string, mixed>
     */
    private function formatearLineaConCatalogo(mixed $linea, array $infoVariaciones): array
    {
        $idVar = (int) $linea->id_variacion;
        $datos = [
            'id_linea' => $linea->id_linea,
            'id_subpedido' => $linea->id_subpedido,
            'id_variacion' => $idVar,
            'id_reserva' => $linea->id_reserva,
            'cantidad' => $linea->cantidad,
            'precio_unitario' => (float) $linea->precio_unitario,
            'tarifa_impuesto' => (float) $linea->tarifa_impuesto,
            'descuento' => (float) ($linea->descuento ?? 0.0),
            'id_promocion' => $linea->id_promocion !== null ? (int) $linea->id_promocion : null,
        ];

        if (isset($infoVariaciones[$idVar])) {
            return array_merge($datos, $infoVariaciones[$idVar]);
        }

        return $datos;
    }

    /**
     * @param  list<array<string, mixed>>  $atributos
     */
    private function formatearAtributosVariacion(array $atributos): string
    {
        $partes = [];
        foreach ($atributos as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $tipo = trim((string) ($attr['tipo_atributo'] ?? $attr['nombre'] ?? ''));
            $valor = trim((string) ($attr['valor'] ?? ''));
            if ($valor === '') {
                continue;
            }
            $partes[] = $tipo !== '' ? "{$tipo}: {$valor}" : $valor;
        }

        return implode(', ', $partes);
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
