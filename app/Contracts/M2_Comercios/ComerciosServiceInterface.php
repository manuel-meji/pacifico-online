<?php

declare(strict_types=1);

namespace App\Contracts\M2_Comercios;

use App\Exceptions\Nucleo\ErrorNucleoException;

/**
 * Interfaz PHP de M2 — Micrositios y administración de comercios.
 *
 * Publica las 8 operaciones del contrato OpenAPI v1.2.1
 * (contracts/m2-comercios/v1/openapi.yaml), con las mismas reglas que la vía HTTP:
 * - Las consultas públicas solo devuelven comercios en estado Activo.
 * - Errores con ErrorNucleoException y el código en la propiedad codigo:
 *   ERR-004 si el recurso no existe (o el comercio no está habilitado, en las
 *   consultas públicas) y ERR-005 con el detalle por campo.
 * - Por esta vía no hay 401 ni 403: cada consumidor autoriza en su propia ruta.
 *
 * Los dobles de los consumidores extienden App\Services\M2_Comercios\Dobles\ComerciosDobleBase.
 */
interface ComerciosServiceInterface
{
    /**
     * Comercios habilitados, paginados. Con $destacado, solo los que tienen publicidad
     * vigente, la más reciente primero.
     *
     * @return array{pagina: int, por_pagina: int, total: int, comercios: list<array{id_comercio: int, nombre_comercial: string, logo_url: ?string, estado: string, destacado: bool}>}
     *
     * @throws ErrorNucleoException ERR-005 si $pagina < 1 o $porPagina no está entre 1 y 50.
     */
    public function listarComerciosHabilitados(int $pagina = 1, int $porPagina = 20, bool $destacado = false): array;

    /**
     * Resumen de varios comercios a la vez. Omite los inexistentes o no habilitados y
     * respeta el orden de la solicitud.
     *
     * @param  list<int>  $idsComercio  De 1 a 50 identificadores, sin repetir.
     * @return array{comercios: list<array{id_comercio: int, nombre_comercial: string, logo_url: ?string, estado: string, destacado: bool}>}
     *
     * @throws ErrorNucleoException ERR-005 si la lista está vacía, tiene más de 50 o se repite un identificador.
     */
    public function obtenerResumenComerciosLote(array $idsComercio): array;

    /**
     * Micrositio público (esquema ComercioPublico): descripcion, logo_url, banners, galeria,
     * horarios, contacto, redes_sociales, politicas y estado.
     *
     * @throws ErrorNucleoException ERR-004 si el comercio no existe o no está habilitado.
     */
    public function obtenerDatosComercioPublico(int $idComercio): array;

    /**
     * Estado del comercio, en cualquier estado: id_comercio, estado, activo y motivo_estado.
     *
     * @throws ErrorNucleoException ERR-004 si el comercio no existe.
     */
    public function obtenerComercioActivo(int $idComercio): array;

    /**
     * Sedes del comercio (id_sede = id_ubicacion de M4), cada una con su indicador habilitada.
     *
     * @throws ErrorNucleoException ERR-004 si el comercio no existe.
     */
    public function obtenerSedesComercio(int $idComercio): array;

    /**
     * Políticas del comercio y tarifas de envío (zona, costo y moneda).
     *
     * @throws ErrorNucleoException ERR-004 si el comercio no existe.
     */
    public function obtenerPoliticasEnvio(int $idComercio): array;

    /**
     * Datos fiscales y comisión vigente (RN-010: base de cálculo, el subtotal del subpedido).
     *
     * @throws ErrorNucleoException ERR-004 si el comercio no existe.
     */
    public function obtenerDatosFiscalesComercio(int $idComercio): array;

    /**
     * Comisión calculada para un subpedido despachado: id_subpedido, id_comercio,
     * monto_comision, base_utilizada y moneda.
     *
     * @throws ErrorNucleoException ERR-004 si la comisión todavía no se ha calculado.
     */
    public function obtenerComisionSubpedido(int $idSubpedido): array;
}
