<?php
namespace App\Http\Controllers\M5_Pedidos;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use App\Models\M5_Pedidos\PedidoLinea;

class ControladorPedido extends Controller
{
    private $m2Comercios;
    private $m4Inventario;
    private $m6Pagos;

    public function __construct($comercioService, $inventarioService, $pagosService)
    {
        $this->m2Comercios = $comercioService;
        $this->m4Inventario = $inventarioService;
        $this->m6Pagos = $pagosService;
    }

    public function procesarCheckout(Request $request): JsonResponse
    {
        $datos = $request->all();
        $clienteId = $request->user()->id; //[cite: 18]

        // 1. Validar que el carrito no esté vacío (simplificado)[cite: 18]
        $carritoTieneItems = true; 
        if (!$carritoTieneItems) {
            return response()->json(['error' => 'Carrito vacío o inexistente'], 400); //[cite: 18, 20]
        }

        // 2. Validar estado de los comercios con M2_Comercios (Contrato M2 v1.2.1)[cite: 16, 18]
        $idsComercios = []; // Extraer del carrito
        $comerciosActivos = $this->m2Comercios->ObtenerResumenComerciosLote($idsComercios);
        
        if (!$comerciosActivos) {
            return response()->json(['error' => 'Comercio no disponible'], 409); //[cite: 18, 20]
        }

        // 3. Reservar existencias en M4_Inventario (Contrato M4 v1.1.0)[cite: 16, 18]
        $idempotencyKey = Str::uuid()->toString();
        $idReserva = $this->m4Inventario->reservarExistencias($idempotencyKey);
        
        if (!$idReserva) {
            return response()->json(['error' => 'Reserva fallida'], 409); //[cite: 18, 20]
        }

        // 4. Calcular costos y crear jerarquía del pedido (Patrón Composite)[cite: 19, 27]
        $montoTotal = $this->calcularCostos(100.0, 13.0, 5.0); // Valores de ejemplo
        
        $pedidoMaestro = new PedidoMaestro();
        $idPedido = $pedidoMaestro->crearPedidoMaestro($montoTotal); // Estado: confirmado[cite: 19]

        // 5. Bucle de creación de subpedidos y líneas[cite: 19]
        $comerciosInvolucrados = []; // Extraer de la agrupación del carrito
        $subpedidoObj = new Subpedido();
        $subpedidosCreados = $subpedidoObj->crearSubpedidos($comerciosInvolucrados);

        foreach ($subpedidosCreados as $subpedido) {
            // Por cada ítem del subpedido[cite: 19]
            (new PedidoLinea())->crearLinea($subpedido['id_subpedido'], 1, 2, 50.0, 13.0); 
        }

        // 6. Publicar evento en el Bus de Eventos[cite: 19]
        event(new \App\Events\PedidoConfirmado($idPedido, $montoTotal)); // Versión v1.0.0[cite: 19]

        // 7. Procesar pago con M6_Pagos (Contrato M6 v1.0.0)[cite: 16, 19]
        $urlPasarelaPago = $this->m6Pagos->ProcesarPagoPedido(
            $idPedido, 
            $datos['metodoPago'] ?? 'tarjeta', 
            $montoTotal, 
            $idempotencyKey
        );

        // 8. Retornar URL de la pasarela para redirigir al cliente[cite: 19]
        return response()->json([
            'pedido' => $pedidoMaestro,
            'urlPasarelaPago' => $urlPasarelaPago
        ], 201); //[cite: 19]
    }

    public function registrarPagoConfirmado(int $pedidoId, string $refPago): JsonResponse
    {
        // 1. Consultar estado actual del pedido[cite: 20]
        $pedido = PedidoMaestro::findOrFail($pedidoId);

        // 2. Operación Idempotente: Si ya está pagado, ignorar sin cambios[cite: 20]
        if ($pedido->estado_pedido === 'pagado') {
            return response()->json(['mensaje' => 'Operación idempotente, pedido ya pagado'], 200); //[cite: 20]
        }

        // 3. Actualizar estado del Pedido Maestro a "pagado"[cite: 20]
        $pedido->actualizarEstado('pagado');

        // 4. Actualizar estado de todos los subpedidos a "pagado"[cite: 20]
        foreach ($pedido->subpedidos as $subpedido) {
            $subpedido->cambiarEstado('pagado'); //[cite: 20]
        }

        // 5. Publicar evento PedidoPagado v1.0.0[cite: 20]
        event(new \App\Events\PedidoPagado($pedidoId, $refPago)); //[cite: 20]

        return response()->json(['mensaje' => 'Pago registrado correctamente'], 200); //[cite: 20]
    }

    public function cancelarSubpedido(int $subpedidoId, string $motivo): JsonResponse
    {
        // Lógica de cancelación[cite: 13]
    }

    public function calcularCostos(float $subtotal, float $impuestos, float $envio): float
    {
        return $subtotal + $impuestos + $envio; //[cite: 13]
    }
}