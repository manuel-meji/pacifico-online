<?php

namespace App\Services\M5_Pedidos\Dobles;

use App\Contracts\M1_Portal\Exceptions\ClienteNoEncontradoException;
use App\Contracts\M1_Portal\Exceptions\DireccionNoEncontradaException;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Models\M1_Portal\Cliente;
use App\Models\M1_Portal\DireccionCliente;

/**
 * Doble de prueba formal (Stub) para M1 Portal público y búsqueda.
 * Implementa tanto PortalServiceInterface como PortalPublicoContract (contrato M1 v1.1.0).
 *
 * Provee datos en memoria para clientes, direcciones y consultas de catálogo público
 * garantizando que M5 no dependa de que M1 tenga expuestas rutas HTTP en integración.
 */
class PortalStub implements PortalPublicoContract, PortalServiceInterface
{
    private bool $simularFallo = false;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $clientes;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $direcciones;

    /**
     * Promociones vigentes por variación para pruebas de integración (contrato M1 v1.4.0).
     *
     * @var array<int, array{id_variacion: int, precio_base: float, precio_final: float, id_promocion: int|null, fin_promocion: string|null}>
     */
    private array $promocionesVariacion = [];

    private int $siguienteIdDireccion = 10;

    public function __construct()
    {
        $this->clientes = [
            1 => [
                'id_cliente' => 1,
                'id_usuario' => 1,
                'nombre' => 'Ana',
                'apellidos' => 'Mora Rojas',
                'correo_electronico' => 'ana.mora@example.com',
                'telefono' => '8888-0001',
                'direccion_envio' => 'Av. Central Calle 5, 200 m norte del parque, Carmen, Central, San José',
            ],
            2 => [
                'id_cliente' => 2,
                'id_usuario' => 2,
                'nombre' => 'Carlos',
                'apellidos' => 'Jiménez Vargas',
                'correo_electronico' => 'carlos.jimenez@example.com',
                'telefono' => '8888-0002',
                'direccion_envio' => 'Del hospital 100 m sur, San Pedro, Montes de Oca, San José',
            ],
            42 => [
                'id_cliente' => 42,
                'id_usuario' => 42,
                'nombre' => 'Juan',
                'apellidos' => 'Pérez González',
                'correo_electronico' => 'juan.perez@test.com',
                'telefono' => '8888-0042',
                'direccion_envio' => 'Avenida Central, Calle 5, Carmen, Central, San José',
            ],
        ];

        $this->direcciones = [
            1 => [
                'id_direccion_cliente' => 1,
                'id_cliente' => 1,
                'alias_direccion' => 'Casa (Principal)',
                'nombre_destinatario' => 'Ana Mora Rojas',
                'telefono_destinatario' => '8888-0001',
                'provincia' => 'San José',
                'canton' => 'Central',
                'distrito' => 'Carmen',
                'otras_senas' => 'Av. Central Calle 5, 200 m norte del parque',
                'es_principal' => true,
                'texto_completo' => 'Casa (Principal) — San José, Central, Carmen, Av. Central Calle 5, 200 m norte del parque',
            ],
            2 => [
                'id_direccion_cliente' => 2,
                'id_cliente' => 1,
                'alias_direccion' => 'Trabajo / Oficina',
                'nombre_destinatario' => 'Ana Mora Rojas',
                'telefono_destinatario' => '8888-0001',
                'provincia' => 'San José',
                'canton' => 'Montes de Oca',
                'distrito' => 'San Pedro',
                'otras_senas' => 'Del hospital 100 m sur, Oficinas Pacíficos #3',
                'es_principal' => false,
                'texto_completo' => 'Trabajo / Oficina — San José, Montes de Oca, San Pedro, Del hospital 100 m sur, Oficinas Pacíficos #3',
            ],
            99 => [
                'id_direccion_cliente' => 99,
                'id_cliente' => 42,
                'alias_direccion' => 'Casa',
                'nombre_destinatario' => 'Juan Pérez',
                'telefono_destinatario' => '8888-0042',
                'provincia' => 'San José',
                'canton' => 'Central',
                'distrito' => 'Carmen',
                'otras_senas' => 'Avenida Central, Calle 5',
                'es_principal' => true,
                'texto_completo' => 'Casa — San José, Central, Carmen, Avenida Central, Calle 5',
            ],
        ];
    }

    public function simularFallo(bool $fallar = true): self
    {
        $this->simularFallo = $fallar;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Métodos de Cliente y Direcciones (Consumidos por M5 según contrato M1 v1.1.0)
    // -------------------------------------------------------------------------

    public function obtenerCliente(int $idCliente): array
    {
        if ($this->simularFallo) {
            throw ErrorProveedorException::datosInvalidos('Error al consultar cliente en M1');
        }

        if (! isset($this->clientes[$idCliente])) {
            throw new ClienteNoEncontradoException("Cliente #{$idCliente} no encontrado.");
        }

        return $this->clientes[$idCliente];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarDireccionesCliente(int $idCliente): array
    {
        if ($this->simularFallo) {
            throw ErrorProveedorException::datosInvalidos('Error al consultar direcciones en M1');
        }

        // 1. Si existe el modelo real de M1, obtener las direcciones reales registradas en base de datos
        if (class_exists(DireccionCliente::class)) {
            try {
                $dirsDb = DireccionCliente::where('id_cliente', $idCliente)->get();
                if ($dirsDb->isNotEmpty()) {
                    $lista = [];
                    foreach ($dirsDb as $d) {
                        $lista[] = [
                            'id_direccion_cliente' => (int) $d->id_direccion_cliente,
                            'id_cliente' => (int) $d->id_cliente,
                            'alias_direccion' => (string) $d->alias_direccion,
                            'nombre_destinatario' => (string) $d->nombre_destinatario,
                            'telefono_destinatario' => (string) $d->telefono_destinatario,
                            'provincia' => (string) $d->provincia,
                            'canton' => (string) $d->canton,
                            'distrito' => (string) $d->distrito,
                            'otras_senas' => (string) $d->otras_senas,
                            'es_principal' => (bool) $d->es_principal,
                            'texto_completo' => "{$d->alias_direccion} — {$d->provincia}, {$d->canton}, {$d->distrito}, {$d->otras_senas}",
                        ];
                    }
                    usort($lista, fn ($a, $b) => ($b['es_principal'] <=> $a['es_principal']));

                    return $lista;
                }
            } catch (\Throwable) {
                // Si la BD falla o no está conectada, continuar con datos de prueba
            }
        }

        $resultado = [];
        foreach ($this->direcciones as $dir) {
            if ($dir['id_cliente'] === $idCliente) {
                $resultado[] = $dir;
            }
        }

        // Si el cliente no tiene direcciones registradas en el stub, generar una por defecto
        if (empty($resultado)) {
            $defecto = [
                'id_direccion_cliente' => 1,
                'id_cliente' => $idCliente,
                'alias_direccion' => 'Principal',
                'nombre_destinatario' => 'Cliente Pacíficos',
                'telefono_destinatario' => '8888-0000',
                'provincia' => 'San José',
                'canton' => 'Central',
                'distrito' => 'Carmen',
                'otras_senas' => 'Avenida Central',
                'es_principal' => true,
                'texto_completo' => 'Principal — San José, Central, Carmen, Avenida Central',
            ];
            $resultado[] = $defecto;
        }

        // Ordenar colocando la principal primero
        usort($resultado, fn ($a, $b) => ($b['es_principal'] <=> $a['es_principal']));

        return $resultado;
    }

    public function obtenerDireccionCliente(int $idCliente, int $idDireccionCliente): array
    {
        if ($this->simularFallo) {
            throw ErrorProveedorException::datosInvalidos('Error al consultar dirección en M1');
        }

        if (class_exists(DireccionCliente::class)) {
            try {
                $dirDb = DireccionCliente::where('id_cliente', $idCliente)
                    ->where('id_direccion_cliente', $idDireccionCliente)
                    ->first();
                if ($dirDb !== null) {
                    return [
                        'id_direccion_cliente' => (int) $dirDb->id_direccion_cliente,
                        'id_cliente' => (int) $dirDb->id_cliente,
                        'alias_direccion' => (string) $dirDb->alias_direccion,
                        'nombre_destinatario' => (string) $dirDb->nombre_destinatario,
                        'telefono_destinatario' => (string) $dirDb->telefono_destinatario,
                        'provincia' => (string) $dirDb->provincia,
                        'canton' => (string) $dirDb->canton,
                        'distrito' => (string) $dirDb->distrito,
                        'otras_senas' => (string) $dirDb->otras_senas,
                        'es_principal' => (bool) $dirDb->es_principal,
                        'texto_completo' => "{$dirDb->alias_direccion} — {$dirDb->provincia}, {$dirDb->canton}, {$dirDb->distrito}, {$dirDb->otras_senas}",
                    ];
                }
            } catch (\Throwable) {
                // Fallback
            }
        }

        if (isset($this->direcciones[$idDireccionCliente])) {
            $dir = $this->direcciones[$idDireccionCliente];
            if ($dir['id_cliente'] === $idCliente) {
                return $dir;
            }
        }

        // Respaldo de tolerancia para pruebas
        if ($idDireccionCliente === 1) {
            return $this->direcciones[1];
        }

        throw new DireccionNoEncontradaException("Dirección #{$idDireccionCliente} no encontrada para cliente #{$idCliente}.");
    }

    public function obtenerIdClientePorUsuario(int $idUsuario): int
    {
        // 1. Si existe en la base de datos real el modelo Cliente de M1, obtener el id_cliente real asociado al id_usuario
        if (class_exists(Cliente::class)) {
            try {
                $clienteReal = Cliente::where('id_usuario', $idUsuario)->first();
                if ($clienteReal !== null) {
                    return (int) $clienteReal->id_cliente;
                }
            } catch (\Throwable) {
                // Si la BD falla, continuar con lógica en memoria
            }
        }

        if ($idUsuario === 501) {
            return 1;
        }

        foreach ($this->clientes as $c) {
            if (($c['id_usuario'] ?? null) === $idUsuario) {
                return (int) $c['id_cliente'];
            }
        }

        return $idUsuario;
    }

    /**
     * Configura una promoción vigente sobre una variación en el doble de M1.
     */
    public function definirPromocionVariacion(
        int $idVariacion,
        float $precioBase,
        float $precioFinal,
        ?int $idPromocion = 1,
        ?string $finPromocion = null
    ): self {
        $this->promocionesVariacion[$idVariacion] = [
            'id_variacion' => $idVariacion,
            'precio_base' => round($precioBase, 2),
            'precio_final' => round($precioFinal, 2),
            'id_promocion' => $idPromocion,
            'fin_promocion' => $finPromocion ?? now()->addDay()->toIso8601String(),
        ];

        return $this;
    }

    /**
     * Elimina todas las promociones configuradas en el doble (p. ej. para simular vencimiento).
     */
    public function limpiarPromociones(): self
    {
        $this->promocionesVariacion = [];

        return $this;
    }

    /**
     * Precios vigentes por variación considerando promociones activas de M1 (contrato v1.4.0 / SC-08).
     *
     * @param  list<int>  $idsVariacion
     * @return array<int, array{id_variacion: int, precio_base: float, precio_final: float, id_promocion: int|null, fin_promocion: string|null}>
     */
    public function obtenerPreciosVigentes(array $idsVariacion): array
    {
        if ($this->simularFallo) {
            throw ErrorProveedorException::datosInvalidos('Error al consultar precios vigentes en M1');
        }

        $resultado = [];
        foreach ($idsVariacion as $idVariacion) {
            $id = (int) $idVariacion;
            if (isset($this->promocionesVariacion[$id])) {
                $resultado[$id] = $this->promocionesVariacion[$id];
            }
        }

        return $resultado;
    }

    public function registrarCliente(array $datos): int
    {
        $id = count($this->clientes) + 1;
        $this->clientes[$id] = array_merge($datos, ['id_cliente' => $id]);

        return $id;
    }

    public function actualizarCliente(int $idCliente, array $datos): int
    {
        $this->clientes[$idCliente] = array_merge($this->clientes[$idCliente] ?? ['id_cliente' => $idCliente], $datos);

        return $idCliente;
    }

    public function agregarDireccionCliente(int $idCliente, array $datos): array
    {
        $idDir = ++$this->siguienteIdDireccion;
        $dir = array_merge($datos, [
            'id_direccion_cliente' => $idDir,
            'id_cliente' => $idCliente,
            'texto_completo' => self::formatearTextoDireccion($datos),
        ]);
        $this->direcciones[$idDir] = $dir;

        return $dir;
    }

    public function actualizarDireccionCliente(int $idCliente, int $idDireccionCliente, array $datos): array
    {
        $existente = $this->obtenerDireccionCliente($idCliente, $idDireccionCliente);
        $actualizada = array_merge($existente, $datos, [
            'texto_completo' => self::formatearTextoDireccion(array_merge($existente, $datos)),
        ]);
        $this->direcciones[$idDireccionCliente] = $actualizada;

        return $actualizada;
    }

    public static function formatearTextoDireccion(array $dir): string
    {
        $alias = $dir['alias_direccion'] ?? 'Dirección';
        $prov = $dir['provincia'] ?? 'San José';
        $cant = $dir['canton'] ?? 'Central';
        $dist = $dir['distrito'] ?? '';
        $senas = $dir['otras_senas'] ?? '';

        $partes = array_filter([$prov, $cant, $dist, $senas]);

        return $alias.' — '.implode(', ', $partes);
    }

    // -------------------------------------------------------------------------
    // Métodos de PortalServiceInterface (Destacados y Búsqueda)
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Métodos de navegación requeridos por PortalPublicoContract
    // -------------------------------------------------------------------------

    public function buscarProductos(?string $termino, ?int $idCategoria = null, ?int $idComercio = null, int $pagina = 1, int $porPagina = 20, ?int $idMarca = null, ?float $precioMinimo = null, ?float $precioMaximo = null, ?string $orden = null): array
    {
        return [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_resultados' => 1,
            'productos' => [
                [
                    'id_producto' => 1,
                    'nombre' => $termino ? "Producto coincidente con {$termino}" : 'Producto destacado',
                    'precio_desde' => 5000.0,
                    'id_comercio' => $idComercio ?? 1,
                ],
            ],
        ];
    }

    public function listarDestacados(int $limite = 12): array
    {
        $base = $this->getDestacados();

        return [
            'comercios' => $base['comercios_destacados'] ?? [],
            'productos' => $base['productos_destacados'] ?? [],
            'secciones_no_disponibles' => [],
        ];
    }

    public function listarCategoriasPortal(): array
    {
        return [
            [
                'id_categoria' => 1,
                'nombre' => 'Alimentos y Bebidas',
                'icono_url' => null,
                'orden_visualizacion' => 1,
                'subcategorias' => [
                    ['id_categoria' => 10, 'nombre' => 'Café'],
                ],
            ],
        ];
    }

    public function obtenerFichaProducto(int $idProducto): array
    {
        return [
            'id_producto' => $idProducto,
            'nombre' => 'Café Tarrazú Gourmet',
            'descripcion' => 'Café de altura cosechado en Tarrazú.',
            'id_comercio' => 1,
            'precio_desde' => 4500.0,
            'variaciones' => [],
        ];
    }

    public function listarComercios(int $pagina = 1, int $porPagina = 20): array
    {
        return [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => 1,
            'comercios' => [
                [
                    'id_comercio' => 1,
                    'nombre_comercial' => 'Comercio Central',
                    'estado' => 'activo',
                ],
            ],
        ];
    }

    public function obtenerMicrositio(int $idComercio, int $pagina = 1, int $porPagina = 20): array
    {
        return [
            'comercio' => [
                'id_comercio' => $idComercio,
                'nombre_comercial' => 'Comercio Central',
            ],
            'productos' => [
                'total' => 1,
                'items' => [],
            ],
        ];
    }
}
