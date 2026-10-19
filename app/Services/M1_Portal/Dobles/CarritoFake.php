<?php

namespace App\Services\M1_Portal\Dobles;

use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use Illuminate\Support\Str;
use LogicException;

/**
 * Fake de M5 Carrito y pedidos (contrato 1.1.0) para las operaciones que
 * consume M1: obtenerCarrito y agregarItemCarrito.
 *
 * Las firmas son las de x-interfaz-php del contrato de M5. Guarda el carrito
 * en memoria: lo agregado se ve en obtenerCarrito mientras viva la instancia
 * (una petición o una prueba). Valida contra el catálogo y las existencias de
 * DatosPrueba, como M5 lo hace con M3.obtenerVariacion y M4.consultarStockDisponible.
 */
class CarritoFake implements PedidosServiceInterface
{
    /**
     * @var array<int, array{id_carrito: int, id_cliente: int, created_at: string, updated_at: string, items: list<array<string, mixed>>}>
     */
    private array $carritos;

    private int $siguienteIdCarrito = 600;

    private int $siguienteIdItem = 9100;

    public function __construct()
    {
        $this->carritos = DatosPrueba::carritos();
    }

    /**
     * GET /carrito. Si el cliente no tiene carrito, se crea uno vacío.
     *
     * @return array<string, mixed> Estructura del esquema Carrito.
     */
    public function obtenerCarrito(int $idCliente): array
    {
        return $this->conMontoEstimado($this->carritoDe($idCliente));
    }

    /**
     * POST /carrito/items. Agrega una línea nueva con la variación y cantidad.
     *
     * Errores: ERR-005 si los datos son inválidos o la variación no existe o no
     * está a la venta; ERR-M5-001 si M4 no tiene unidades suficientes.
     *
     * @param  array{id_variacion?: mixed, cantidad?: mixed, id_reserva?: mixed}  $datos
     * @return array<string, mixed> Estructura del esquema Carrito actualizado.
     */
    public function agregarItemCarrito(int $idCliente, array $datos): array
    {
        $carrito = $this->carritoDe($idCliente);
        $cantidad = $datos['cantidad'] ?? 1;
        $idReserva = $datos['id_reserva'] ?? null;

        $this->validarDatos($datos, $cantidad, $idReserva);

        $encontrada = DatosPrueba::buscarVariacion($datos['id_variacion']);

        if ($encontrada === null
            || ! DatosPrueba::comercioActivo($encontrada['producto']['id_comercio'])
            || ! $encontrada['variacion']['disponible']) {
            throw ErrorProveedorException::datosInvalidos(['id_variacion' => 'La variación no existe o no está disponible para la venta.']);
        }

        if ($this->unidadesDisponibles($encontrada['producto']['id_comercio'], $datos['id_variacion']) < $cantidad) {
            throw new ErrorProveedorException('ERR-M5-001', 'Uno o más productos no cuentan con existencias disponibles.');
        }

        $ahora = now()->toIso8601String();

        $carrito['items'][] = [
            'id_item_carrito' => $this->siguienteIdItem++,
            'id_carrito' => $carrito['id_carrito'],
            'id_variacion' => $datos['id_variacion'],
            'cantidad' => $cantidad,
            'id_reserva' => $idReserva,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
        $carrito['updated_at'] = $ahora;

        $this->carritos[$idCliente] = $carrito;

        return $this->conMontoEstimado($carrito);
    }

    public function crearPedido(array $datosCompra): array
    {
        throw new LogicException('Fuera del alcance del doble de M1.');
    }

    public function marcarComoPagado(int $pedidoId, string $comprobantePago): bool
    {
        throw new LogicException('Fuera del alcance del doble de M1.');
    }

    public function despacharSubpedido(int $subpedidoId, ?string $guiaTransporte = null): array
    {
        throw new LogicException('Fuera del alcance del doble de M1.');
    }

    /**
     * @return array{id_carrito: int, id_cliente: int, created_at: string, updated_at: string, items: list<array<string, mixed>>}
     */
    private function carritoDe(int $idCliente): array
    {
        if ($idCliente < 1) {
            throw ErrorProveedorException::datosInvalidos(['id_cliente' => 'Debe ser un entero mayor o igual a 1.']);
        }

        if (! isset($this->carritos[$idCliente])) {
            $ahora = now()->toIso8601String();
            $this->carritos[$idCliente] = [
                'id_carrito' => $this->siguienteIdCarrito++,
                'id_cliente' => $idCliente,
                'created_at' => $ahora,
                'updated_at' => $ahora,
                'items' => [],
            ];
        }

        return $this->carritos[$idCliente];
    }

    /**
     * monto_estimado con los precios vigentes del catálogo.
     *
     * @param  array<string, mixed>  $carrito
     * @return array<string, mixed>
     */
    private function conMontoEstimado(array $carrito): array
    {
        $monto = 0.0;

        foreach ($carrito['items'] as $item) {
            $monto += DatosPrueba::buscarVariacion($item['id_variacion'])['variacion']['precio'] * $item['cantidad'];
        }

        return [
            'id_carrito' => $carrito['id_carrito'],
            'id_cliente' => $carrito['id_cliente'],
            'monto_estimado' => $monto,
            'created_at' => $carrito['created_at'],
            'updated_at' => $carrito['updated_at'],
            'items' => $carrito['items'],
        ];
    }

    private function unidadesDisponibles(int $idComercio, int $idVariacion): int
    {
        $unidades = 0;

        foreach (DatosPrueba::existencias() as $existencia) {
            if ($existencia['id_comercio'] === $idComercio && $existencia['id_variacion'] === $idVariacion) {
                $unidades += $existencia['cantidad_disponible'];
            }
        }

        return $unidades;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarDatos(array $datos, mixed $cantidad, mixed $idReserva): void
    {
        $errores = [];

        if (! is_int($datos['id_variacion'] ?? null) || $datos['id_variacion'] < 1) {
            $errores['id_variacion'] = 'Es obligatorio y debe ser un entero mayor o igual a 1.';
        }

        if (! is_int($cantidad) || $cantidad < 1) {
            $errores['cantidad'] = 'Debe ser un número entero mayor o igual a 1.';
        }

        if ($idReserva !== null && (! is_string($idReserva) || ! Str::isUuid($idReserva))) {
            $errores['id_reserva'] = 'Debe ser un UUID.';
        }

        if ($errores !== []) {
            throw ErrorProveedorException::datosInvalidos($errores);
        }
    }
}
