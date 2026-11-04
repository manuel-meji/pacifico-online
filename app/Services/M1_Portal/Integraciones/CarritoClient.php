<?php

namespace App\Services\M1_Portal\Integraciones;

use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M5_Pedidos\CarritoServiceInterface;
use Throwable;

/**
 * Adaptador del contrato de M5 Carrito y pedidos (agregarItemCarrito y
 * obtenerCarrito), para el botón Agregar al carrito de la ficha y el contador
 * del carrito del encabezado. Corta la espera a los 3 s (RNF-M1-04). No usa la
 * caché: el carrito cambia con cada acción de la persona.
 */
class CarritoClient
{
    use LlamaProveedor;

    public function __construct(private CarritoServiceInterface $contrato)
    {
        $this->cargarTiempoMaximo();
    }

    /**
     * @throws DatosInvalidosException ERR-005 si M5 rechaza la variación o la
     *                                 cantidad, incluido ERR-M5-001 (sin unidades suficientes).
     */
    public function agregarItem(int $idCliente, int $idVariacion, int $cantidad): void
    {
        $this->llamar(
            'M5',
            fn (): array => $this->contrato->agregarItemCarrito($idCliente, [
                'id_variacion' => $idVariacion,
                'cantidad' => $cantidad,
            ]),
            fn (?string $codigo, Throwable $error): ?DatosInvalidosException => match ($codigo) {
                'ERR-005' => new DatosInvalidosException($this->detallesDe($error), $error->getMessage(), $error),
                'ERR-M5-001' => new DatosInvalidosException(['cantidad' => $error->getMessage()], $error->getMessage(), $error),
                default => null,
            },
        );
    }

    /**
     * Unidades en el carrito del cliente (suma de las cantidades de sus líneas).
     */
    public function contarItems(int $idCliente): int
    {
        $carrito = $this->llamar('M5', fn (): array => $this->contrato->obtenerCarrito($idCliente));

        return (int) array_sum(array_column($carrito['items'] ?? [], 'cantidad'));
    }
}
