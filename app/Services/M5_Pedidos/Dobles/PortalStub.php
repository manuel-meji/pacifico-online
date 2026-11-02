<?php

namespace App\Services\M5_Pedidos\Dobles;

use App\Contracts\M1_Portal\PortalServiceInterface;

/**
 * Doble de prueba formal (Stub) para M1 Portal público y búsqueda.
 * Implementa App\Contracts\M1_Portal\PortalServiceInterface.
 */
class PortalStub implements PortalServiceInterface
{
    private bool $simularFallo = false;

    public function simularFallo(bool $fallar = true): self
    {
        $this->simularFallo = $fallar;

        return $this;
    }

    /**
     * Obtener productos y comercios destacados para la página de inicio.
     *
     * @return array<string, mixed>
     */
    public function getDestacados(): array
    {
        if ($this->simularFallo) {
            throw ErrorProveedorException::datosInvalidos('Error al consultar destacados');
        }

        return [
            'productos_destacados' => [
                [
                    'id_producto' => 1,
                    'nombre' => 'Café Tarrazú Gourmet',
                    'precio_desde' => 4500.0,
                    'comercio' => 'Café Don Manuel',
                ],
            ],
            'comercios_destacados' => [
                [
                    'id_comercio' => 2,
                    'nombre_comercial' => 'Comercio de prueba',
                    'logo_url' => 'https://pacifico.test/logos/2.png',
                ],
            ],
        ];
    }

    /**
     * Búsqueda pública de productos por término y filtros.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function buscar(string $query, array $filtros = []): array
    {
        if ($this->simularFallo || trim($query) === '') {
            throw ErrorProveedorException::datosInvalidos('Término de búsqueda inválido o vacío');
        }

        return [
            'query' => $query,
            'total' => 1,
            'resultados' => [
                [
                    'id_producto' => 1,
                    'nombre' => 'Producto coincidente con '.$query,
                    'precio' => 15000.0,
                    'id_comercio' => 2,
                ],
            ],
        ];
    }
}
