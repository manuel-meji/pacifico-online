<?php

namespace App\Services\M1_Portal\Integraciones;

use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Services\M1_Portal\DTO\ComercioDTO;
use App\Services\M1_Portal\DTO\EstadoComercio;
use App\Services\M1_Portal\DTO\ListadoComerciosDTO;
use Throwable;

/**
 * Adaptador del contrato de M2 Micrositios y comercios
 * (ObtenerDatosComercioPublico, ObtenerResumenComerciosLote y
 * ListarComerciosHabilitados): traduce a ComercioDTO, guarda cada comercio en
 * la caché y corta la espera a los 3 s (RNF-M1-04).
 *
 * Mientras M2 no publique estas operaciones en ComerciosServiceInterface, se
 * llaman con las firmas que propone M1 (las mismas de ComerciosStub); si el
 * servicio real no las tiene, se informa ERR-006.
 */
class ComercioClient
{
    use LlamaProveedor;

    private const MAXIMO_LOTE_M2 = 50;

    public function __construct(
        private ComerciosServiceInterface $contrato,
        private CachePortal $cache,
    ) {
        $this->cargarTiempoMaximo();
    }

    /**
     * M2 solo responde comercios Activo (RN-M1-01).
     *
     * @throws RecursoNoEncontradoException ERR-004 si el comercio no existe o no está Activo.
     */
    public function obtenerDatosComercioPublico(int $idComercio): ComercioDTO
    {
        $clave = sprintf(CachePortal::CLAVE_COMERCIO_PUBLICO, $idComercio);
        $comercio = $this->cache->obtener($clave);

        if ($comercio === null) {
            $comercio = $this->llamar(
                'M2',
                fn (): array => $this->contrato->obtenerDatosComercioPublico($idComercio),
                fn (?string $codigo, Throwable $error): ?RecursoNoEncontradoException => $codigo === 'ERR-004'
                    ? new RecursoNoEncontradoException($error->getMessage(), $error)
                    : null,
            );
            $this->cache->guardar($clave, $comercio);
        }

        return $this->publico($comercio);
    }

    /**
     * Nombre, logotipo y estado de varios comercios (resultados de búsqueda y
     * destacados). Se omiten los inexistentes o no habilitados y se conserva
     * el orden de $ids.
     *
     * @param  list<int>  $ids
     * @return list<ComercioDTO>
     */
    public function obtenerResumenComercios(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        $resumenes = [];
        $faltantes = [];

        foreach ($ids as $id) {
            $guardado = $this->cache->obtener(sprintf(CachePortal::CLAVE_COMERCIO_RESUMEN, $id));

            if ($guardado === null) {
                $faltantes[] = $id;
            } else {
                $resumenes[$id] = $guardado;
            }
        }

        foreach (array_chunk($faltantes, self::MAXIMO_LOTE_M2) as $lote) {
            $respuesta = $this->llamar('M2', fn (): array => $this->contrato->obtenerResumenComerciosLote($lote));

            foreach ($respuesta['comercios'] as $comercio) {
                $resumenes[$comercio['id_comercio']] = $comercio;
                $this->cache->guardar(sprintf(CachePortal::CLAVE_COMERCIO_RESUMEN, $comercio['id_comercio']), $comercio);
            }
        }

        $resultado = [];

        foreach ($ids as $id) {
            if (isset($resumenes[$id])) {
                $resultado[] = $this->resumen($resumenes[$id]);
            }
        }

        return $resultado;
    }

    public function listarComerciosHabilitados(int $pagina, int $porPagina): ListadoComerciosDTO
    {
        $respuesta = $this->llamar('M2', fn (): array => $this->contrato->listarComerciosHabilitados($pagina, $porPagina));

        return new ListadoComerciosDTO(
            comercios: array_map(fn (array $comercio): ComercioDTO => $this->resumen($comercio), $respuesta['comercios']),
            total: $respuesta['total'],
            pagina: $respuesta['pagina'],
            porPagina: $respuesta['por_pagina'],
        );
    }

    /**
     * @param  array<string, mixed>  $comercio  Esquema ComercioPublico de M2.
     */
    private function publico(array $comercio): ComercioDTO
    {
        $imagenes = array_column([...($comercio['banners'] ?? []), ...($comercio['galeria'] ?? [])], 'imagen_url');

        return new ComercioDTO(
            idComercio: $comercio['id_comercio'],
            nombreComercial: $comercio['nombre_comercial'],
            logotipo: $comercio['logo_url'] ?? null,
            descripcion: $comercio['descripcion'] ?? null,
            imagenes: $imagenes,
            horarios: $comercio['horarios'] ?? [],
            contacto: $comercio['contacto'] ?? [],
            redesSociales: $comercio['redes_sociales'] ?? [],
            politicas: $comercio['politicas'] ?? [],
            estado: $this->estado($comercio['estado'] ?? null),
            banners: $this->imagenes($comercio['banners'] ?? []),
            galeria: $this->imagenes($comercio['galeria'] ?? []),
        );
    }

    /**
     * Banners o galería de M2: solo las imágenes con URL, con su texto alternativo.
     *
     * @param  list<array<string, mixed>>|null  $imagenes
     * @return list<array{imagen_url: string, texto_alternativo: ?string}>
     */
    private function imagenes(?array $imagenes): array
    {
        $resultado = [];

        foreach ($imagenes ?? [] as $imagen) {
            if (is_string($imagen['imagen_url'] ?? null) && $imagen['imagen_url'] !== '') {
                $resultado[] = [
                    'imagen_url' => $imagen['imagen_url'],
                    'texto_alternativo' => $imagen['texto_alternativo'] ?? null,
                ];
            }
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $comercio  Resumen de M2 (lote o listado).
     */
    private function resumen(array $comercio): ComercioDTO
    {
        return new ComercioDTO(
            idComercio: $comercio['id_comercio'],
            nombreComercial: $comercio['nombre_comercial'],
            logotipo: $comercio['logo_url'] ?? null,
            descripcion: null,
            imagenes: [],
            horarios: [],
            contacto: [],
            redesSociales: [],
            politicas: [],
            estado: $this->estado($comercio['estado'] ?? null),
        );
    }

    /**
     * Las operaciones públicas de M2 solo devuelven comercios Activo; un valor
     * desconocido se trata como Pendiente para no mostrarlo por error.
     */
    private function estado(?string $estado): EstadoComercio
    {
        return EstadoComercio::tryFrom((string) $estado) ?? EstadoComercio::Pendiente;
    }
}
