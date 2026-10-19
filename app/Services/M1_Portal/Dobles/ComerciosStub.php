<?php

namespace App\Services\M1_Portal\Dobles;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use LogicException;

/**
 * Stub de M2 Micrositios y comercios (contrato 1.2.1) para las operaciones que
 * consume M1: ObtenerDatosComercioPublico, ObtenerResumenComerciosLote y
 * ListarComerciosHabilitados.
 *
 * El contrato de M2 no publica firmas PHP; las de esta clase son las
 * propuestas por M1 a partir de las operaciones REST, y se ajustan cuando M2
 * las agregue a ComerciosServiceInterface.
 */
class ComerciosStub implements ComerciosServiceInterface
{
    private const MAXIMO_LOTE = 50;

    private const MAXIMO_POR_PAGINA = 50;

    /**
     * GET /comercios/{id_comercio}/publico. Solo responde comercios Activo; los
     * demás estados y los inexistentes lanzan ERR-004.
     *
     * @return array<string, mixed> Estructura del esquema ComercioPublico.
     */
    public function obtenerDatosComercioPublico(int $idComercio): array
    {
        if ($idComercio < 1) {
            throw ErrorProveedorException::datosInvalidos(['id_comercio' => 'Debe ser un entero mayor o igual a 1.']);
        }

        if (! DatosPrueba::comercioActivo($idComercio)) {
            throw ErrorProveedorException::noEncontrado('El comercio no existe o no está habilitado.');
        }

        $comercio = DatosPrueba::comercios()[$idComercio];
        unset($comercio['destacado'], $comercio['inicio_publicidad']);

        return $comercio;
    }

    /**
     * POST /comercios/publico/lote. Omite los identificadores inexistentes o no
     * habilitados y conserva el orden de la solicitud.
     *
     * @param  list<int>  $idsComercio  Entre 1 y 50 identificadores sin repetir.
     * @return array{comercios: list<array{id_comercio: int, nombre_comercial: string, logo_url: string|null, estado: string, destacado: bool}>}
     */
    public function obtenerResumenComerciosLote(array $idsComercio): array
    {
        $this->validarLote($idsComercio);

        $comercios = [];

        foreach ($idsComercio as $idComercio) {
            if (DatosPrueba::comercioActivo($idComercio)) {
                $comercios[] = $this->resumen(DatosPrueba::comercios()[$idComercio]);
            }
        }

        return ['comercios' => $comercios];
    }

    /**
     * GET /comercios/publico. Solo comercios Activo. Con destacado=true devuelve
     * los que tienen publicidad vigente, la más reciente primero.
     *
     * @return array{pagina: int, por_pagina: int, total: int, comercios: list<array<string, mixed>>}
     */
    public function listarComerciosHabilitados(int $pagina = 1, int $porPagina = 20, bool $destacado = false): array
    {
        $errores = [];

        if ($pagina < 1) {
            $errores['pagina'] = 'Debe ser un entero mayor o igual a 1.';
        }

        if ($porPagina < 1 || $porPagina > self::MAXIMO_POR_PAGINA) {
            $errores['por_pagina'] = 'Debe estar entre 1 y '.self::MAXIMO_POR_PAGINA.'.';
        }

        if ($errores !== []) {
            throw ErrorProveedorException::datosInvalidos($errores);
        }

        $comercios = array_filter(
            DatosPrueba::comercios(),
            fn (array $comercio): bool => $comercio['estado'] === 'Activo' && (! $destacado || $comercio['destacado']),
        );

        if ($destacado) {
            usort($comercios, fn (array $a, array $b): int => strcmp($b['inicio_publicidad'], $a['inicio_publicidad']));
        }

        $comercios = array_values($comercios);

        return [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => count($comercios),
            'comercios' => array_map(
                fn (array $comercio): array => $this->resumen($comercio),
                array_slice($comercios, ($pagina - 1) * $porPagina, $porPagina),
            ),
        ];
    }

    public function obtenerMicrositio(int $comercioId): array
    {
        throw new LogicException('Fuera del alcance del doble de M1.');
    }

    public function registrarComision(int $pedidoId, float $subtotalPedido): bool
    {
        throw new LogicException('Fuera del alcance del doble de M1.');
    }

    /**
     * @param  array<string, mixed>  $comercio
     * @return array{id_comercio: int, nombre_comercial: string, logo_url: string|null, estado: string, destacado: bool}
     */
    private function resumen(array $comercio): array
    {
        return [
            'id_comercio' => $comercio['id_comercio'],
            'nombre_comercial' => $comercio['nombre_comercial'],
            'logo_url' => $comercio['logo_url'],
            'estado' => $comercio['estado'],
            'destacado' => $comercio['destacado'],
        ];
    }

    /**
     * @param  array<mixed>  $idsComercio
     */
    private function validarLote(array $idsComercio): void
    {
        if ($idsComercio === [] || count($idsComercio) > self::MAXIMO_LOTE) {
            throw ErrorProveedorException::datosInvalidos(['ids_comercio' => 'Debe tener entre 1 y '.self::MAXIMO_LOTE.' elementos.']);
        }

        foreach ($idsComercio as $idComercio) {
            if (! is_int($idComercio)) {
                throw ErrorProveedorException::datosInvalidos(['ids_comercio' => 'Todos los elementos deben ser enteros.']);
            }
        }

        if (count(array_unique($idsComercio)) !== count($idsComercio)) {
            throw ErrorProveedorException::datosInvalidos(['ids_comercio' => 'No se permiten identificadores repetidos.']);
        }
    }
}
