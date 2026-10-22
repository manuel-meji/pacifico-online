<?php
namespace App\Http\Controllers\M5_Pedidos;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;

class ControladorCarrito extends Controller
{
    private $m3Catalogo;
    private $m4Inventario;

    public function __construct($catalogoService, $inventarioService)
    {
        $this->m3Catalogo = $catalogoService;
        $this->m4Inventario = $inventarioService;
    }

    public function agregarAlCarrito(Request $request, int $variacionId, int $cant): JsonResponse
    {
        // 1. Obtener clienteId del token autenticado[cite: 17]
        $clienteId = $request->user()->id; 

        // 2. Consultar detalles de la variación al M3_Catalogo (Contrato M3 v1.2.1)[cite: 16, 17]
        $datosVariacion = $this->m3Catalogo->obtenerVariacion($variacionId);
        
        if (!$datosVariacion) {
            return response()->json(['error' => 'Variación inexistente'], 422); //[cite: 17, 20]
        }

        // 3. Verificar existencias en M4_Inventario (Contrato M4 v1.1.0)[cite: 16, 17]
        $stockDisponible = $this->m4Inventario->consultarStockDisponible(
            $datosVariacion['id_comercio'], 
            $variacionId, 
            $datosVariacion['id_ubicacion'] ?? 1 
        );

        // 4. Evaluar disponibilidad[cite: 17]
        if (!$stockDisponible) {
            return response()->json(['error' => 'Existencia insuficiente'], 422); //[cite: 17, 20]
        }

        // 5. Lógica interna de Entidades: Obtener o crear carrito y agregar ítem[cite: 17]
        $carrito = (new Carrito())->obtenerOCrearCarrito($clienteId);
        $item = (new ItemCarrito())->crearItemCarrito($carrito->id_carrito, $variacionId, $cant);

        // 6. Recalcular costos[cite: 17]
        $this->agruparItemPorComercio();
        // $subtotales e $impuestos se obtendrían de los ítems agrupados
        $this->calcularCostosPedidos(0.0, 0.0); 
        $carrito->recalcularTotales();

        // 7. Retornar respuesta exitosa[cite: 17]
        return response()->json([
            'mensaje' => 'Carrito actualizado',
            'carrito' => $carrito->load('items')
        ], 201);
    }

    public function eliminarItem(int $clienteId, int $itemId): JsonResponse
    {
        // Lógica de eliminación (CU-001)[cite: 12]
    }

    public function calcularCostosPedidos(float $subtotales, float $impuestos): float
    {
        // Lógica de cálculo de costos[cite: 12]
        return $subtotales + $impuestos;
    }

    public function agruparItemPorComercio(): void
    {
        // Lógica para agrupar ítems[cite: 12]
    }
}