<?php

namespace App\Services\M5_Pedidos;

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
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoLinea;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        ?object $portalOverride = null
    ): array {
        $catalogo = $catalogoOverride ?? $this->catalogoService ?? (app()->bound(CatalogoServiceInterface::class) ? app(CatalogoServiceInterface::class) : null);
        $comercios = $comerciosOverride ?? $this->comerciosService ?? (app()->bound(ComerciosServiceInterface::class) ? app(ComerciosServiceInterface::class) : null);
        $portal = $portalOverride ?? $this->resolverPortalM1();

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
                                if (! empty($politicas['tarifas'][0]['costo'])) {
                                    $costoEnvioComercio = (float) $politicas['tarifas'][0]['costo'];
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
                    'lineas' => $subpedido->lineas->map(fn ($l) => [
                        'id_linea' => $l->id_linea,
                        'id_subpedido' => $l->id_subpedido,
                        'id_variacion' => $l->id_variacion,
                        'id_reserva' => $l->id_reserva,
                        'cantidad' => $l->cantidad,
                        'precio_unitario' => (float) $l->precio_unitario,
                        'tarifa_impuesto' => (float) $l->tarifa_impuesto,
                        'descuento' => (float) ($l->descuento ?? 0.0),
                        'id_promocion' => $l->id_promocion !== null ? (int) $l->id_promocion : null,
                    ])->all(),
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
            $subpedido = Subpedido::with('lineas')->find($subpedidoId);
            if ($subpedido) {
                $subpedido->despachar($guiaTransporte);
                $pedidoId = (int) $subpedido->id_pedido;
                $subtotal = (float) $subpedido->subtotal;
                $this->confirmarSalidaLineas($subpedido);
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
                    $this->solicitarReembolsoM6((int) $subpedido->id_pedido, $montoReembolso, $motivo);
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
        $cupon = $datosCheckout['cupon_descuento'] ?? null;
        $tarjetaRegalo = $datosCheckout['tarjeta_regalo'] ?? null;
        $idReservaDefault = $datosCheckout['id_reserva'] ?? null;

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

            $pedidoCreado = DB::transaction(function () use (
                $idCliente,
                $idDireccion,
                $cupon,
                $tarjetaRegalo,
                $idReservaDefault,
                $carrito,
                $grupos,
                $resumen
            ): PedidoMaestro {
                $subtotalMaestro = 0.0;
                $impuestoMaestro = 0.0;
                $envioMaestro = 0.0;
                $descuentoMaestro = 0.0;

                if (is_array($resumen)) {
                    $subtotalMaestro = round((float) ($resumen['subtotal'] ?? 0.0), 2);
                    $impuestoMaestro = round((float) ($resumen['impuesto'] ?? 0.0), 2);
                    $envioMaestro = round((float) ($resumen['costo_envio'] ?? 0.0), 2);
                } elseif (is_array($grupos)) {
                    foreach ($grupos as $g) {
                        $subtotalMaestro = round($subtotalMaestro + (float) ($g['subtotal'] ?? 0.0), 2);
                        $impuestoMaestro = round($impuestoMaestro + (float) ($g['impuesto'] ?? 0.0), 2);
                        $envioMaestro = round($envioMaestro + (float) ($g['costo_envio'] ?? 0.0), 2);
                    }
                }

                $montoTotal = round($subtotalMaestro + $envioMaestro + $impuestoMaestro - $descuentoMaestro, 2);

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
                            $lineaReserva = (string) ($itemData['id_reserva'] ?? $idReservaDefault ?? '');
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
            if ($pedido) {
                return [
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
                    'subpedidos' => $pedido->subpedidos->map(fn ($sub) => [
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
                        'lineas' => $sub->lineas->map(fn ($l) => [
                            'id_linea' => $l->id_linea,
                            'id_subpedido' => $l->id_subpedido,
                            'id_variacion' => $l->id_variacion,
                            'id_reserva' => $l->id_reserva,
                            'cantidad' => $l->cantidad,
                            'precio_unitario' => (float) $l->precio_unitario,
                            'tarifa_impuesto' => (float) $l->tarifa_impuesto,
                            'descuento' => (float) ($l->descuento ?? 0.0),
                            'id_promocion' => $l->id_promocion !== null ? (int) $l->id_promocion : null,
                        ])->all(),
                    ])->all(),
                    'historial_estados' => $pedido->estados?->toArray() ?? [],
                    'cancelaciones' => $pedido->cancelaciones?->toArray() ?? [],
                ];
            }
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
            $pedido = PedidoMaestro::withoutGlobalScope('cliente')
                ->with(['subpedidos' => fn ($q) => $q->withoutGlobalScope('comercio')->with('lineas')])
                ->find($pedidoId);
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
                $this->confirmarReservasLineas($subpedido->lineas, (int) $pedido->id_pedido, (int) $subpedido->id_subpedido);
            }

            PedidoPagado::dispatch(
                $pedido->fresh(['subpedidos' => fn ($q) => $q->withoutGlobalScope('comercio')->with('lineas')]) ?? $pedido
            );
        }

        return true;
    }

    /**
     * Libera en M4 las reservas asociadas a una colección de líneas de pedido.
     *
     * @param  iterable<PedidoLinea>  $lineas
     */
    public function liberarReservasLineas(iterable $lineas, string $motivo = 'pedido_cancelado'): void
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
    public function confirmarReservasLineas(iterable $lineas, ?int $idPedidoExplicito = null, ?int $idSubpedidoExplicito = null): void
    {
        $inventario = $this->inventarioService ?? (app()->bound(InventarioServiceInterface::class) ? app(InventarioServiceInterface::class) : null);
        if (! $inventario) {
            return;
        }

        foreach ($lineas as $linea) {
            if (! empty($linea->id_reserva)) {
                try {
                    if (method_exists($inventario, 'confirmarReserva')) {
                        $idSubpedido = $idSubpedidoExplicito ?? (int) $linea->id_subpedido;
                        $idPedido = $idPedidoExplicito ?? (int) ($linea->subpedido()->withoutGlobalScope('comercio')->value('id_pedido') ?? $linea->subpedido?->id_pedido ?? 0);

                        if ($idPedido > 0 && $idSubpedido > 0) {
                            // Contrato M4 v1.3.0: confirmarReserva(id_reserva, id_pedido, id_subpedido).
                            $inventario->confirmarReserva(
                                (string) $linea->id_reserva,
                                $idPedido,
                                $idSubpedido,
                            );
                        }
                    }
                } catch (\Throwable) {
                }
            }
        }
    }

    /**
     * Confirma en M4 la salida física de inventario para las reservas de un subpedido despachado.
     */
    public function confirmarSalidaLineas(Subpedido $subpedido): void
    {
        $inventario = $this->inventarioService ?? (app()->bound(InventarioServiceInterface::class) ? app(InventarioServiceInterface::class) : null);
        if (! $inventario || ! method_exists($inventario, 'confirmarSalidaPorReserva')) {
            return;
        }

        $subpedido->loadMissing('lineas');
        $idPedido = (int) $subpedido->id_pedido;
        $idSubpedido = (int) $subpedido->id_subpedido;

        foreach ($subpedido->lineas as $linea) {
            if (! empty($linea->id_reserva)) {
                try {
                    $inventario->confirmarSalidaPorReserva(
                        (string) $linea->id_reserva,
                        $idPedido,
                        $idSubpedido,
                    );
                } catch (\Throwable) {
                }
            }
        }
    }

    /**
     * Solicita el reembolso del monto a M6 cuando se cancela un pedido/subpedido ya pagado.
     */
    public function solicitarReembolsoM6(int $idPedido, float $monto, string $motivo): void
    {
        $pagos = $this->pagosService ?? (app()->bound(PagosServiceInterface::class) ? app(PagosServiceInterface::class) : null);
        if (! $pagos) {
            return;
        }

        try {
            if (method_exists($pagos, 'solicitarReembolso')) {
                $pagos->solicitarReembolso($idPedido, $monto, $motivo);
            } elseif (method_exists($pagos, 'reembolsarPago')) {
                $pagos->reembolsarPago($idPedido, $monto, $motivo);
            }
        } catch (\Throwable) {
        }
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
