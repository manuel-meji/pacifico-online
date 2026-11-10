<?php

declare(strict_types=1);

namespace App\Http\Controllers\M5_Pedidos;

use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\ItemDeseo;
use App\Services\Nucleo\ContextoCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControladorDeseos extends Controller
{
    private ?CatalogoServiceInterface $m3Catalogo;

    private ?object $m1Portal;

    public function __construct(
        ?CatalogoServiceInterface $catalogoService = null,
        ?PortalServiceInterface $portalService = null
    ) {
        $this->m3Catalogo = $catalogoService ?? (app()->bound(CatalogoServiceInterface::class) ? app(CatalogoServiceInterface::class) : null);
        $this->m1Portal = $portalService ?? (app()->bound(PortalPublicoContract::class) ? app(PortalPublicoContract::class) : (app()->bound(PortalServiceInterface::class) ? app(PortalServiceInterface::class) : null));
    }

    public function resolverIdCliente(Request $request): int
    {
        $usuario = $request->user();

        if ($usuario !== null) {
            $idUsuario = (int) ($usuario->id_usuario ?? $usuario->id ?? 0);

            if ($idUsuario > 0 && $this->m1Portal && method_exists($this->m1Portal, 'obtenerIdClientePorUsuario')) {
                $idCliente = (int) $this->m1Portal->obtenerIdClientePorUsuario($idUsuario);
                if (app()->bound(ContextoCliente::class)) {
                    app(ContextoCliente::class)->establecer($idCliente);
                }

                return $idCliente;
            }
        }

        return (int) $request->input('id_cliente', 1);
    }

    /**
     * Listar todos los productos en la lista de deseos del cliente.
     */
    public function index(Request $request): JsonResponse
    {
        $idCliente = $this->resolverIdCliente($request);
        $deseos = ItemDeseo::where('id_cliente', $idCliente)->orderBy('created_at', 'desc')->get();

        $items = [];
        foreach ($deseos as $deseo) {
            $detallesVariacion = null;
            if ($this->m3Catalogo) {
                try {
                    $detallesVariacion = $this->m3Catalogo->obtenerVariacion((int) $deseo->id_variacion);
                } catch (\Throwable) {
                    $detallesVariacion = null;
                }
            }

            $items[] = [
                'id_deseo' => (int) $deseo->id_deseo,
                'id_cliente' => (int) $deseo->id_cliente,
                'id_variacion' => (int) $deseo->id_variacion,
                'fecha_agregado' => $deseo->created_at?->toISOString(),
                'producto' => $detallesVariacion,
            ];
        }

        return response()->json([
            'id_cliente' => $idCliente,
            'total' => count($items),
            'items' => $items,
        ], 200);
    }

    /**
     * Agregar una variación a la lista de deseos.
     */
    public function store(Request $request): JsonResponse
    {
        $idCliente = $this->resolverIdCliente($request);
        $idVariacion = (int) $request->input('id_variacion');

        if ($idVariacion <= 0) {
            return response()->json([
                'error' => [
                    'codigo' => 'ERR-005',
                    'mensaje' => 'Debe indicar una variación válida.',
                    'detalles' => null,
                ],
            ], 422);
        }

        $deseo = ItemDeseo::firstOrCreate(
            ['id_cliente' => $idCliente, 'id_variacion' => $idVariacion]
        );

        return response()->json([
            'mensaje' => 'Producto agregado a la lista de deseos',
            'item' => [
                'id_deseo' => (int) $deseo->id_deseo,
                'id_cliente' => (int) $deseo->id_cliente,
                'id_variacion' => (int) $deseo->id_variacion,
            ],
        ], 201);
    }

    /**
     * Eliminar una variación de la lista de deseos.
     */
    public function destroy(Request $request, int $idDeseo): JsonResponse
    {
        $idCliente = $this->resolverIdCliente($request);
        $deseo = ItemDeseo::where('id_cliente', $idCliente)->where('id_deseo', $idDeseo)->first();

        if (! $deseo) {
            // Permitir eliminar también por id_variacion si se pasa directamente
            $deseo = ItemDeseo::where('id_cliente', $idCliente)->where('id_variacion', $idDeseo)->first();
        }

        if ($deseo) {
            $deseo->delete();
        }

        return response()->json([
            'mensaje' => 'Producto eliminado de la lista de deseos',
        ], 200);
    }

    /**
     * Mover de la lista de deseos al carrito de compras.
     */
    public function moverAlCarrito(Request $request, int $idDeseo): JsonResponse
    {
        $idCliente = $this->resolverIdCliente($request);
        $deseo = ItemDeseo::where('id_cliente', $idCliente)->where('id_deseo', $idDeseo)->first();

        if (! $deseo) {
            $deseo = ItemDeseo::where('id_cliente', $idCliente)->where('id_variacion', $idDeseo)->first();
        }

        if (! $deseo) {
            return response()->json([
                'error' => [
                    'codigo' => 'ERR-004',
                    'mensaje' => 'El elemento no existe en su lista de deseos.',
                    'detalles' => null,
                ],
            ], 404);
        }

        $idVariacion = (int) $deseo->id_variacion;
        $carrito = (new Carrito)->obtenerOCrearCarrito($idCliente);
        (new ItemCarrito)->crearItemCarrito((int) $carrito->id_carrito, $idVariacion, 1);

        $deseo->delete();

        return response()->json([
            'mensaje' => 'Producto movido al carrito correctamente',
            'id_variacion' => $idVariacion,
        ], 200);
    }
}
