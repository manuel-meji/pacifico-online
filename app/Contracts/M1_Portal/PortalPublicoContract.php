<?php

namespace App\Contracts\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\ClienteNoEncontradoException;
use App\Contracts\M1_Portal\Exceptions\CorreoYaRegistradoException;
use App\Contracts\M1_Portal\Exceptions\CriterioInvalidoException;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Contracts\M1_Portal\Exceptions\DireccionNoEncontradaException;
use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;

/**
 * Interfaz PHP publicada de M1 Portal público y búsqueda (contrato v1.1.0,
 * contracts/m1-portal/v1/openapi.yaml, sección x-interfaz-php).
 *
 * - M5 consume obtenerCliente, listarDireccionesCliente y obtenerDireccionCliente.
 * - M6 consume obtenerCliente.
 * - El núcleo consume obtenerIdClientePorUsuario (middleware EstablecerCliente).
 * - Los demás métodos los usan los controladores de M1, que son una capa
 *   delgada sobre esta interfaz; cada uno corresponde a la ruta web con el
 *   mismo operationId.
 *
 * Los arreglos que se reciben y devuelven siguen los esquemas del contrato
 * (snake_case). Toda excepción extiende PortalPublicoException, que expone
 * codigo() con el código de error de la ruta web equivalente.
 *
 * Doble de prueba para los consumidores: tests/Doubles/M1/PortalPublicoFake.php.
 */
interface PortalPublicoContract
{
    // -----------------------------------------------------------------
    // Métodos para otros módulos (M5, M6 y núcleo)
    // -----------------------------------------------------------------

    /**
     * Datos de la persona compradora. Sin sesión de usuario; puede llamarse
     * desde procesos en segundo plano. Desde 1.0.0, sin cambios en 1.1.0.
     *
     * @return array{id_cliente: int, nombre: string, apellidos: string, correo_electronico: string, telefono: string, direccion_envio: string} Esquema Cliente.
     *
     * @throws ClienteNoEncontradoException ERR-004.
     */
    public function obtenerCliente(int $idCliente): array;

    /**
     * Direcciones de envío del cliente; la principal va primero. Desde 1.1.0.
     *
     * @return list<array{id_direccion_cliente: int, id_cliente: int, alias_direccion: string, nombre_destinatario: string, telefono_destinatario: string, provincia: string, canton: string, distrito: string, otras_senas: string, es_principal: bool}> Esquema DireccionCliente.
     *
     * @throws ClienteNoEncontradoException ERR-004.
     */
    public function listarDireccionesCliente(int $idCliente): array;

    /**
     * Una dirección del cliente. Pensado para que M5 valide el
     * id_direccion_cliente que recibe al confirmar el pedido. Desde 1.1.0.
     *
     * @return array{id_direccion_cliente: int, id_cliente: int, alias_direccion: string, nombre_destinatario: string, telefono_destinatario: string, provincia: string, canton: string, distrito: string, otras_senas: string, es_principal: bool} Esquema DireccionCliente.
     *
     * @throws DireccionNoEncontradaException ERR-004. También si la dirección no pertenece a ese cliente.
     */
    public function obtenerDireccionCliente(int $idCliente, int $idDireccionCliente): array;

    /**
     * id_cliente asociado a la cuenta de acceso (clientes.id_usuario es único).
     * Sin sesión de usuario. Desde 1.1.0.
     *
     * @throws ClienteNoEncontradoException ERR-004, si la cuenta no tiene un cliente asociado.
     */
    public function obtenerIdClientePorUsuario(int $idUsuario): int;

    // -----------------------------------------------------------------
    // Métodos de uso interno (controladores de M1)
    // -----------------------------------------------------------------

    /**
     * Búsqueda de productos publicados de comercios habilitados (GET
     * /productos/busqueda). Los filtros de 1.1.0 van al final como opcionales,
     * así las llamadas de 1.0.0 siguen funcionando. $orden admite precio_asc,
     * precio_desc o novedad.
     *
     * @return array{pagina: int, por_pagina: int, total_resultados: int, productos: list<array<string, mixed>>} Esquema ResultadoBusqueda.
     *
     * @throws CriterioInvalidoException ERR-005.
     * @throws DependenciaNoDisponibleException ERR-006.
     */
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
    ): array;

    /**
     * Comercios y productos destacados de la página principal (GET /destacados).
     * Si M2 o M3 fallan, la sección viene vacía y se nombra en
     * secciones_no_disponibles; no lanza excepción.
     *
     * @return array{comercios: list<array<string, mixed>>, productos: list<array<string, mixed>>, secciones_no_disponibles: list<string>} Esquema Destacados.
     */
    public function listarDestacados(int $limite = 8): array;

    /**
     * Categorías del menú con sus subcategorías (GET /categorias). Solo las
     * que tienen al menos un producto visible (RN-M1-09).
     *
     * @return list<array{id_categoria: int, nombre: string, icono_url: string|null, orden_visualizacion: int, subcategorias: list<array{id_categoria: int, nombre: string}>}> Esquema CategoriaPortal.
     *
     * @throws DependenciaNoDisponibleException ERR-006.
     */
    public function listarCategoriasPortal(): array;

    /**
     * Página de un producto con variaciones y disponibilidad (GET /productos/{id_producto}).
     *
     * @return array<string, mixed> Esquema FichaProducto.
     *
     * @throws RecursoNoEncontradoException ERR-004.
     * @throws DependenciaNoDisponibleException ERR-006.
     */
    public function obtenerFichaProducto(int $idProducto): array;

    /**
     * Listado de comercios habilitados (GET /comercios).
     *
     * @return array{pagina: int, por_pagina: int, total: int, comercios: list<array<string, mixed>>} Esquema ListadoComercios.
     *
     * @throws DependenciaNoDisponibleException ERR-006.
     */
    public function listarComercios(int $pagina = 1, int $porPagina = 20): array;

    /**
     * Micrositio de un comercio con sus productos (GET /comercios/{id_comercio}/micrositio).
     *
     * @return array{comercio: array<string, mixed>, productos: array<string, mixed>} Esquema Micrositio.
     *
     * @throws RecursoNoEncontradoException ERR-004.
     * @throws DependenciaNoDisponibleException ERR-006.
     */
    public function obtenerMicrositio(int $idComercio, int $pagina = 1, int $porPagina = 20): array;

    /**
     * Registra a la persona compradora y crea su cuenta en el núcleo (POST
     * /clientes). $datos sigue el esquema RegistroCliente (1.2.0): datos del
     * cliente, cedula, contrasena y la dirección desglosada en 'direccion',
     * que se guarda como la principal. La contraseña se entrega al núcleo y
     * M1 no la guarda.
     *
     * @param  array<string, mixed>  $datos
     * @return int id_cliente creado.
     *
     * @throws CorreoYaRegistradoException ERR-M1-001.
     * @throws DatosInvalidosException ERR-005.
     */
    public function registrarCliente(array $datos): int;

    /**
     * Actualiza los datos de la persona compradora (PUT /clientes/{id_cliente}).
     * Desde 1.2.0 no cambia la cédula ni las direcciones.
     *
     * @param  array<string, mixed>  $datos  Esquema DatosCliente.
     * @return int id_cliente actualizado.
     *
     * @throws ClienteNoEncontradoException ERR-004.
     * @throws CorreoYaRegistradoException ERR-M1-001.
     * @throws DatosInvalidosException ERR-005.
     */
    public function actualizarCliente(int $idCliente, array $datos): int;

    /**
     * Agrega una dirección de envío (POST /clientes/{id_cliente}/direcciones).
     *
     * @param  array<string, mixed>  $datos  Esquema DatosDireccionCliente.
     * @return array<string, mixed> Esquema DireccionCliente.
     *
     * @throws ClienteNoEncontradoException ERR-004.
     * @throws DatosInvalidosException ERR-005.
     */
    public function agregarDireccionCliente(int $idCliente, array $datos): array;

    /**
     * Actualiza una dirección de envío (PUT /clientes/{id_cliente}/direcciones/{id_direccion_cliente}).
     *
     * @param  array<string, mixed>  $datos  Esquema DatosDireccionCliente.
     * @return array<string, mixed> Esquema DireccionCliente.
     *
     * @throws DireccionNoEncontradaException ERR-004.
     * @throws DatosInvalidosException ERR-005.
     */
    public function actualizarDireccionCliente(int $idCliente, int $idDireccionCliente, array $datos): array;
}
