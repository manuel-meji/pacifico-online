<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Services\M1_Portal\DTO\CategoriaDTO;
use App\Services\M1_Portal\DTO\ClienteDTO;
use App\Services\M1_Portal\DTO\ComercioDTO;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\M1_Portal\DTO\DestacadosDTO;
use App\Services\M1_Portal\DTO\DireccionClienteDTO;
use App\Services\M1_Portal\DTO\FichaProductoDTO;
use App\Services\M1_Portal\DTO\ListadoComerciosDTO;
use App\Services\M1_Portal\DTO\MicrositioDTO;
use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use App\Services\M1_Portal\DTO\ResultadoBusquedaDTO;
use App\Services\M1_Portal\DTO\VariacionDTO;
use Illuminate\Contracts\Container\Container;

/**
 * Fachada de M1 (PortalPublicoService, sección 4.1 del PC3). Implementa
 * PortalPublicoContract: delega cada operación en el servicio de su caso de
 * uso y convierte el DTO que este devuelve al arreglo del esquema del
 * contrato (contracts/m1-portal/v1/openapi.yaml). Los arreglos que recibe se
 * validan contra el esquema y se pasan al servicio como DTO.
 *
 * Cada servicio se resuelve solo cuando se usa: si todavía no existe, esa
 * operación lanza DependenciaNoDisponibleException (ERR-006) y las demás
 * siguen funcionando. listarDestacados no lanza: devuelve las secciones
 * vacías y las marca en secciones_no_disponibles, como pide el contrato.
 */
class PortalPublicoService implements PortalPublicoContract
{
    public const SERVICIO_BUSQUEDA = BusquedaService::class;

    public const SERVICIO_FICHA = FichaProductoService::class;

    public const SERVICIO_MICROSITIO = MicrositioService::class;

    public const SERVICIO_PORTADA = PortadaService::class;

    public const SERVICIO_CLIENTE = ClienteService::class;

    /**
     * Campos obligatorios y largo máximo de DatosCliente (contrato 1.2.0),
     * iguales a las columnas de la tabla clientes. El teléfono es obligatorio.
     * direccion_envio ya no es de entrada: sale calculada de la dirección
     * principal (tercera forma normal).
     */
    private const CAMPOS_CLIENTE = [
        'nombre' => 80,
        'apellidos' => 120,
        'correo_electronico' => 150,
        'telefono' => 20,
    ];

    /**
     * Largo máximo de clientes.cedula. La cédula es obligatoria al registrarse
     * y única en la tabla.
     */
    private const LARGO_CEDULA = 20;

    /**
     * Largo mínimo de la contraseña; es la misma regla que aplica el núcleo en
     * ServicioIdentidad::crearCuenta para el rol Cliente (min:8).
     */
    private const LARGO_MINIMO_CONTRASENA = 8;

    /**
     * Campos obligatorios y largo máximo de DatosDireccionCliente, iguales a
     * las columnas de la tabla direcciones_cliente.
     */
    private const CAMPOS_DIRECCION = [
        'alias_direccion' => 40,
        'nombre_destinatario' => 150,
        'telefono_destinatario' => 20,
        'provincia' => 40,
        'canton' => 60,
        'distrito' => 60,
        'otras_senas' => 300,
    ];

    /**
     * Formato de direcciones_cliente.telefono_destinatario
     * (chk_direcciones_cliente_telefono).
     */
    private const FORMATO_TELEFONO_DESTINATARIO = '/^[0-9 +-]+$/';

    public function __construct(private Container $contenedor) {}

    // ---------------------------------------------------------------
    // Clientes y direcciones (ClienteService)
    // ---------------------------------------------------------------

    public function obtenerCliente(int $idCliente): array
    {
        return $this->cliente($this->servicio(self::SERVICIO_CLIENTE)->obtener($idCliente));
    }

    public function listarDireccionesCliente(int $idCliente): array
    {
        return array_map(
            fn (DireccionClienteDTO $direccion): array => $this->direccion($direccion),
            $this->servicio(self::SERVICIO_CLIENTE)->listarDirecciones($idCliente),
        );
    }

    public function obtenerDireccionCliente(int $idCliente, int $idDireccionCliente): array
    {
        return $this->direccion($this->servicio(self::SERVICIO_CLIENTE)->obtenerDireccion($idCliente, $idDireccionCliente));
    }

    public function obtenerIdClientePorUsuario(int $idUsuario): int
    {
        return $this->servicio(self::SERVICIO_CLIENTE)->obtenerIdPorUsuario($idUsuario);
    }

    /**
     * $datos sigue el esquema RegistroCliente (contrato 1.2.0): los datos del
     * cliente, la cédula (obligatoria, única, máximo 20), la contraseña y la
     * dirección desglosada en 'direccion', que se guarda como la principal.
     * Los errores de la dirección se informan como 'direccion.<campo>'.
     */
    public function registrarCliente(array $datos): int
    {
        $errores = $this->validarCliente($datos);

        $cedula = $datos['cedula'] ?? null;

        if (! is_string($cedula) || trim($cedula) === '') {
            $errores['cedula'] = 'Es obligatorio.';
        } elseif (mb_strlen(trim($cedula)) > self::LARGO_CEDULA) {
            $errores['cedula'] = 'No puede tener más de '.self::LARGO_CEDULA.' caracteres.';
        }

        $contrasena = $datos['contrasena'] ?? null;

        if (! is_string($contrasena) || $contrasena === '') {
            $errores['contrasena'] = 'Es obligatorio.';
        } elseif (mb_strlen($contrasena) < self::LARGO_MINIMO_CONTRASENA) {
            $errores['contrasena'] = 'Debe tener al menos '.self::LARGO_MINIMO_CONTRASENA.' caracteres.';
        }

        $direccion = $datos['direccion'] ?? null;

        if (! is_array($direccion)) {
            $errores['direccion'] = 'Es obligatorio.';
        } else {
            // La dirección del registro siempre queda como principal.
            unset($direccion['es_principal']);

            foreach ($this->validarDireccion($direccion) as $campo => $mensaje) {
                $errores["direccion.{$campo}"] = $mensaje;
            }
        }

        $this->rechazarSiHayErrores($errores);

        return $this->servicio(self::SERVICIO_CLIENTE)->registrar(
            $this->clienteDTO(null, $datos, trim($cedula)),
            $this->direccionDTO(null, null, $direccion, esPrincipal: true),
            $contrasena,
        );
    }

    /**
     * $datos sigue el esquema DatosCliente. No cambia la cédula ni las
     * direcciones (contrato 1.2.0): se conservan las que el cliente ya tiene.
     */
    public function actualizarCliente(int $idCliente, array $datos): int
    {
        $this->rechazarSiHayErrores($this->validarCliente($datos));

        $servicio = $this->servicio(self::SERVICIO_CLIENTE);
        $actual = $servicio->obtener($idCliente);

        return $servicio->actualizar(
            $idCliente,
            $this->clienteDTO($idCliente, $datos, $actual->cedula, $actual->direccionEnvio),
        );
    }

    public function agregarDireccionCliente(int $idCliente, array $datos): array
    {
        $this->rechazarSiHayErrores($this->validarDireccion($datos));

        return $this->direccion($this->servicio(self::SERVICIO_CLIENTE)->agregarDireccion(
            $idCliente,
            $this->direccionDTO(null, $idCliente, $datos),
        ));
    }

    public function actualizarDireccionCliente(int $idCliente, int $idDireccionCliente, array $datos): array
    {
        $this->rechazarSiHayErrores($this->validarDireccion($datos));

        return $this->direccion($this->servicio(self::SERVICIO_CLIENTE)->actualizarDireccion(
            $idCliente,
            $idDireccionCliente,
            $this->direccionDTO($idDireccionCliente, $idCliente, $datos),
        ));
    }

    // ---------------------------------------------------------------
    // Navegación del portal
    // ---------------------------------------------------------------

    public function buscarProductos(
        ?string $termino,
        ?int $idCategoria = null,
        ?int $idComercio = null,
        int $pagina = 1,
        int $porPagina = 20,
        ?int $idMarca = null,
        ?float $precioMinimo = null,
        ?float $precioMaximo = null,
        ?string $orden = null,
    ): array {
        $criterio = new CriterioBusquedaDTO(
            termino: $termino,
            idCategoria: $idCategoria,
            idComercio: $idComercio,
            idMarca: $idMarca,
            precioMinimo: $precioMinimo,
            precioMaximo: $precioMaximo,
            orden: $orden,
            pagina: $pagina,
            porPagina: $porPagina,
        );

        return $this->resultadoBusqueda($this->servicio(self::SERVICIO_BUSQUEDA)->buscar($criterio));
    }

    public function listarDestacados(int $limite = PortadaService::LIMITE_POR_DEFECTO): array
    {
        try {
            /** @var DestacadosDTO $destacados */
            $destacados = $this->servicio(self::SERVICIO_PORTADA)->listarDestacados($limite);
        } catch (DependenciaNoDisponibleException) {
            $destacados = new DestacadosDTO([], [], ['comercios', 'productos']);
        }

        return [
            'comercios' => array_map(fn (ComercioDTO $comercio): array => $this->comercioListado($comercio), $destacados->comercios),
            'productos' => array_map(fn (ProductoResumenDTO $producto): array => $this->productoResumen($producto), $destacados->productos),
            'secciones_no_disponibles' => array_values($destacados->seccionesNoDisponibles),
        ];
    }

    /**
     * BusquedaService entrega una lista plana de CategoriaDTO, ya filtrada y
     * ordenada. Aquí se arma el menú: cada categoría principal (sin padre)
     * con sus subcategorías debajo, en el mismo orden en que llegaron. Una
     * subcategoría cuyo padre no viene en la lista se descarta.
     */
    public function listarCategoriasPortal(): array
    {
        /** @var list<CategoriaDTO> $categorias */
        $categorias = $this->servicio(self::SERVICIO_BUSQUEDA)->listarCategoriasPortal();
        $menu = [];

        foreach ($categorias as $categoria) {
            if ($categoria->idCategoriaPadre === null) {
                $menu[$categoria->idCategoria] = [
                    'id_categoria' => $categoria->idCategoria,
                    'nombre' => $categoria->nombre,
                    'icono_url' => $categoria->iconoUrl,
                    'orden_visualizacion' => $categoria->ordenVisualizacion ?? count($menu) + 1,
                    'subcategorias' => [],
                ];
            }
        }

        foreach ($categorias as $categoria) {
            if ($categoria->idCategoriaPadre !== null && isset($menu[$categoria->idCategoriaPadre])) {
                $menu[$categoria->idCategoriaPadre]['subcategorias'][] = [
                    'id_categoria' => $categoria->idCategoria,
                    'nombre' => $categoria->nombre,
                ];
            }
        }

        return array_values($menu);
    }

    public function obtenerFichaProducto(int $idProducto): array
    {
        return $this->fichaProducto($this->servicio(self::SERVICIO_FICHA)->obtenerFicha($idProducto));
    }

    public function listarComercios(int $pagina = 1, int $porPagina = 20): array
    {
        /** @var ListadoComerciosDTO $listado */
        $listado = $this->servicio(self::SERVICIO_MICROSITIO)->listarComercios($pagina, $porPagina);

        return [
            'pagina' => $listado->pagina,
            'por_pagina' => $listado->porPagina,
            'total' => $listado->total,
            'comercios' => array_map(fn (ComercioDTO $comercio): array => $this->comercioListado($comercio), $listado->comercios),
        ];
    }

    public function obtenerMicrositio(int $idComercio, int $pagina = 1, int $porPagina = 20): array
    {
        /** @var MicrositioDTO $micrositio */
        $micrositio = $this->servicio(self::SERVICIO_MICROSITIO)->obtenerMicrositio($idComercio, $pagina, $porPagina);

        return [
            'comercio' => $this->comercioMicrositio($micrositio->comercio),
            'productos' => $this->resultadoBusqueda($micrositio->productos),
            'productos_disponibles' => $micrositio->productosDisponibles,
        ];
    }

    // ---------------------------------------------------------------
    // Resolución de servicios
    // ---------------------------------------------------------------

    /**
     * Servicio del caso de uso, resuelto en el momento. Si la clase todavía
     * no existe, la operación no está disponible (ERR-006).
     */
    private function servicio(string $clase): object
    {
        if (! $this->contenedor->bound($clase) && ! class_exists($clase)) {
            throw new DependenciaNoDisponibleException('La operación todavía no está disponible en el portal.');
        }

        return $this->contenedor->make($clase);
    }

    // ---------------------------------------------------------------
    // DTO → arreglo del contrato
    // ---------------------------------------------------------------

    /**
     * Esquema Cliente.
     *
     * @return array<string, mixed>
     */
    private function cliente(ClienteDTO $cliente): array
    {
        return [
            'id_cliente' => $cliente->idCliente,
            'nombre' => $cliente->nombre,
            'apellidos' => $cliente->apellidos,
            'correo_electronico' => $cliente->correoElectronico,
            'telefono' => $cliente->telefono,
            'direccion_envio' => $cliente->direccionEnvio,
        ];
    }

    /**
     * Esquema DireccionCliente.
     *
     * @return array<string, mixed>
     */
    private function direccion(DireccionClienteDTO $direccion): array
    {
        return [
            'id_direccion_cliente' => $direccion->idDireccionCliente,
            'id_cliente' => $direccion->idCliente,
            'alias_direccion' => $direccion->aliasDireccion,
            'nombre_destinatario' => $direccion->nombreDestinatario,
            'telefono_destinatario' => $direccion->telefonoDestinatario,
            'provincia' => $direccion->provincia,
            'canton' => $direccion->canton,
            'distrito' => $direccion->distrito,
            'otras_senas' => $direccion->otrasSenas,
            'es_principal' => $direccion->esPrincipal,
        ];
    }

    /**
     * Esquema ResultadoBusqueda (también es la parte "productos" del micrositio).
     *
     * @return array<string, mixed>
     */
    private function resultadoBusqueda(ResultadoBusquedaDTO $resultado): array
    {
        return [
            'pagina' => $resultado->pagina,
            'por_pagina' => $resultado->porPagina,
            'total_resultados' => $resultado->totalResultados,
            'productos' => array_map(fn (ProductoResumenDTO $producto): array => $this->productoResumen($producto), $resultado->productos),
        ];
    }

    /**
     * Esquema ProductoResumen.
     *
     * @return array<string, mixed>
     */
    private function productoResumen(ProductoResumenDTO $producto): array
    {
        return [
            'id_producto' => $producto->idProducto,
            'nombre' => $producto->nombre,
            'imagen_principal' => $producto->imagenPrincipal,
            'precio' => $producto->precio,
            'precio_hasta' => $producto->precioHasta,
            'moneda' => $producto->moneda,
            'disponibilidad' => $producto->disponibilidad->value,
            'comercio' => [
                'id_comercio' => $producto->idComercio,
                'nombre_comercial' => $producto->nombreComercio,
            ],
        ];
    }

    /**
     * Comercio de ListadoComercios y Destacados.
     *
     * @return array{id_comercio: int, nombre_comercial: string, logotipo: ?string}
     */
    private function comercioListado(ComercioDTO $comercio): array
    {
        return [
            'id_comercio' => $comercio->idComercio,
            'nombre_comercial' => $comercio->nombreComercial,
            'logotipo' => $comercio->logotipo,
        ];
    }

    /**
     * Comercio del esquema Micrositio. Solo lleva los campos que declara el
     * contrato (M2 puede mandar otros, como id_horario o id_banner). Los
     * opcionales que M2 no manda quedan vacíos o en null (CU-009 A3).
     *
     * @return array<string, mixed>
     */
    private function comercioMicrositio(ComercioDTO $comercio): array
    {
        $galeria = $comercio->galeria;

        // Comercio armado sin banners ni galería separados: se muestran sus
        // imágenes como galería.
        if ($comercio->banners === [] && $galeria === []) {
            $galeria = array_map(fn (string $imagen): array => ['imagen_url' => $imagen, 'texto_alternativo' => null], $comercio->imagenes);
        }

        return [
            'id_comercio' => $comercio->idComercio,
            'nombre_comercial' => $comercio->nombreComercial,
            'descripcion' => $comercio->descripcion,
            'logo_url' => $comercio->logotipo,
            'banners' => $this->imagenesMicrositio($comercio->banners, $comercio->nombreComercial),
            'galeria' => $this->imagenesMicrositio($galeria, $comercio->nombreComercial),
            'horarios' => $this->soloCampos($comercio->horarios, ['dia_semana', 'hora_apertura', 'hora_cierre', 'cerrado']),
            'contacto' => [
                'telefono' => $comercio->contacto['telefono'] ?? null,
                'correo' => $comercio->contacto['correo'] ?? null,
                'direccion' => $comercio->contacto['direccion'] ?? null,
            ],
            'redes_sociales' => $this->soloCampos($comercio->redesSociales, ['plataforma', 'url']),
            'politicas' => $this->soloCampos($comercio->politicas, ['tipo', 'descripcion']),
        ];
    }

    /**
     * Banner o imagen de galería: texto_alternativo es texto en el contrato;
     * si M2 no lo manda, se usa el nombre del comercio.
     *
     * @param  list<array<string, mixed>>  $imagenes
     * @return list<array{imagen_url: string, texto_alternativo: string}>
     */
    private function imagenesMicrositio(array $imagenes, string $nombreComercial): array
    {
        return array_map(fn (array $imagen): array => [
            'imagen_url' => $imagen['imagen_url'],
            'texto_alternativo' => $imagen['texto_alternativo'] ?? $nombreComercial,
        ], $imagenes);
    }

    /**
     * Cada elemento de la lista solo con los campos indicados.
     *
     * @param  list<array<string, mixed>>  $elementos
     * @param  list<string>  $campos
     * @return list<array<string, mixed>>
     */
    private function soloCampos(array $elementos, array $campos): array
    {
        return array_values(array_map(
            fn (array $elemento): array => array_intersect_key($elemento, array_flip($campos)),
            $elementos,
        ));
    }

    /**
     * Esquema FichaProducto.
     *
     * @return array<string, mixed>
     */
    private function fichaProducto(FichaProductoDTO $ficha): array
    {
        return [
            'id_producto' => $ficha->idProducto,
            'nombre' => $ficha->nombre,
            'descripcion' => $ficha->descripcion,
            'marca' => $ficha->marca,
            'video_url' => $ficha->videoUrl,
            'moneda' => $ficha->moneda,
            'imagenes' => $ficha->imagenes,
            'variaciones' => array_map(fn (VariacionDTO $variacion): array => [
                'id_variacion' => $variacion->idVariacion,
                'sku' => $variacion->sku,
                'precio' => $variacion->precio,
                'atributos' => $variacion->atributos,
                'disponibilidad' => $variacion->disponibilidad->value,
                'comprable' => $variacion->comprable,
            ], $ficha->variaciones),
            'comercio' => [
                'id_comercio' => $ficha->comercio->idComercio,
                'nombre_comercial' => $ficha->comercio->nombreComercial,
            ],
            'relacionados' => array_map(fn (ProductoResumenDTO $producto): array => $this->productoResumen($producto), $ficha->relacionados),
        ];
    }

    // ---------------------------------------------------------------
    // Arreglo del contrato → DTO, con validación
    // ---------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     */
    private function clienteDTO(?int $idCliente, array $datos, string $cedula, string $direccionEnvio = ''): ClienteDTO
    {
        return new ClienteDTO(
            idCliente: $idCliente,
            nombre: trim($datos['nombre']),
            apellidos: trim($datos['apellidos']),
            correoElectronico: trim($datos['correo_electronico']),
            telefono: trim($datos['telefono']),
            cedula: $cedula,
            direccionEnvio: $direccionEnvio,
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function direccionDTO(?int $idDireccionCliente, ?int $idCliente, array $datos, ?bool $esPrincipal = null): DireccionClienteDTO
    {
        return new DireccionClienteDTO(
            idDireccionCliente: $idDireccionCliente,
            idCliente: $idCliente,
            aliasDireccion: trim($datos['alias_direccion']),
            nombreDestinatario: trim($datos['nombre_destinatario']),
            telefonoDestinatario: trim($datos['telefono_destinatario']),
            provincia: trim($datos['provincia']),
            canton: trim($datos['canton']),
            distrito: trim($datos['distrito']),
            otrasSenas: trim($datos['otras_senas']),
            esPrincipal: $esPrincipal ?? (bool) ($datos['es_principal'] ?? false),
        );
    }

    /**
     * Campos obligatorios, largo máximo y formato del correo (esquema DatosCliente).
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, string>
     */
    private function validarCliente(array $datos): array
    {
        $errores = [];

        foreach (self::CAMPOS_CLIENTE as $campo => $maximo) {
            $valor = $datos[$campo] ?? null;

            if (! is_string($valor) || trim($valor) === '') {
                $errores[$campo] = 'Es obligatorio.';
            } elseif (mb_strlen(trim($valor)) > $maximo) {
                $errores[$campo] = "No puede tener más de {$maximo} caracteres.";
            }
        }

        if (! isset($errores['correo_electronico']) && filter_var(trim($datos['correo_electronico']), FILTER_VALIDATE_EMAIL) === false) {
            $errores['correo_electronico'] = 'No tiene un formato de correo válido.';
        }

        return $errores;
    }

    /**
     * Campos obligatorios del esquema DatosDireccionCliente.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, string>
     */
    private function validarDireccion(array $datos): array
    {
        $errores = [];

        foreach (self::CAMPOS_DIRECCION as $campo => $maximo) {
            $valor = $datos[$campo] ?? null;

            if (! is_string($valor) || trim($valor) === '') {
                $errores[$campo] = 'Es obligatorio.';
            } elseif (mb_strlen(trim($valor)) > $maximo) {
                $errores[$campo] = "No puede tener más de {$maximo} caracteres.";
            }
        }

        if (! isset($errores['telefono_destinatario'])
            && preg_match(self::FORMATO_TELEFONO_DESTINATARIO, trim($datos['telefono_destinatario'])) !== 1) {
            $errores['telefono_destinatario'] = 'Solo admite dígitos, espacios, + y -.';
        }

        if (array_key_exists('es_principal', $datos) && ! is_bool($datos['es_principal'])) {
            $errores['es_principal'] = 'Debe ser verdadero o falso.';
        }

        return $errores;
    }

    /**
     * @param  array<string, string>  $errores
     */
    private function rechazarSiHayErrores(array $errores): void
    {
        if ($errores !== []) {
            throw new DatosInvalidosException($errores);
        }
    }
}
