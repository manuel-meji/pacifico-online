<?php

declare(strict_types=1);

namespace App\Services\M2_Comercios;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\ComisionComercio;
use App\Models\M2_Comercios\ComisionSubpedido;
use App\Models\M2_Comercios\ContactoComercio;
use App\Models\M2_Comercios\DocumentoComercio;
use App\Models\M2_Comercios\PublicidadComercio;
use App\Models\M2_Comercios\SolicitudComercio;
use Illuminate\Support\Facades\Log;

/**
 * Fachada de M2 (patrón Facade, sección 7 del PC3): implementa las 8 operaciones
 * de consulta del contrato OpenAPI v1.2.1 (contracts/m2-comercios/v1/openapi.yaml).
 *
 * Los demás módulos llaman a M2 solo por ComerciosServiceInterface, en el mismo
 * proceso; nunca usan sus modelos ni sus tablas. Por esa vía el contrato no
 * responde 401 ni 403: la autorización la aplica cada consumidor en su ruta, y
 * ConsultasComerciosController la aplica en las rutas HTTP de M2.
 *
 * Errores: ErrorNucleoException con los códigos del núcleo (ERR-004 y ERR-005),
 * que ManejadorErrores convierte en ErrorEstandar y que los adaptadores de los
 * consumidores leen en la propiedad codigo.
 */
class ComerciosService implements ComerciosServiceInterface
{
    public const MODULO = 'M2_Comercios';

    public const MAXIMO_POR_PAGINA = 50;

    public const MAXIMO_LOTE = 50;

    public function __construct(
        private readonly ServicioIdentidadInterface $identidad,
        private readonly NucleoServiceInterface $nucleo,
        private readonly SubpedidosServiceInterface $subpedidos,
    ) {}

    public function listarComerciosHabilitados(int $pagina = 1, int $porPagina = 20, bool $destacado = false): array
    {
        $errores = [];
        if ($pagina < 1) {
            $errores['pagina'] = ['Debe ser un entero mayor o igual a 1.'];
        }
        if ($porPagina < 1 || $porPagina > self::MAXIMO_POR_PAGINA) {
            $errores['por_pagina'] = ['Debe estar entre 1 y '.self::MAXIMO_POR_PAGINA.'.'];
        }
        $this->exigirValidos($errores);

        $consulta = Comercio::query()->habilitados()->with(['micrositio', 'publicidades']);

        if ($destacado) {
            // Contrato: los destacados se ordenan por el inicio de la publicidad vigente, la más reciente primero.
            $consulta->destacados()->orderByDesc(
                PublicidadComercio::query()
                    ->selectRaw('MAX(inicio)')
                    ->whereColumn('publicidad_comercio.id_comercio', 'comercios.id_comercio')
                    ->vigentes()
            );
        }
        $consulta->orderBy('nombre_comercial');

        $total = (clone $consulta)->count();
        $comercios = $consulta->forPage($pagina, $porPagina)->get();

        return [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => $total,
            'comercios' => $comercios->map(fn (Comercio $c): array => $this->resumen($c))->values()->all(),
        ];
    }

    public function obtenerResumenComerciosLote(array $idsComercio): array
    {
        $ids = array_values($idsComercio);
        $errores = [];
        if ($ids === [] || count($ids) > self::MAXIMO_LOTE) {
            $errores['ids_comercio'] = ['Debe tener entre 1 y '.self::MAXIMO_LOTE.' identificadores.'];
        } elseif (array_filter($ids, fn ($id): bool => ! is_int($id)) !== []) {
            $errores['ids_comercio'] = ['Todos los identificadores deben ser enteros.'];
        } elseif (count(array_unique($ids)) !== count($ids)) {
            $errores['ids_comercio'] = ['Los identificadores no se pueden repetir.'];
        }
        $this->exigirValidos($errores);

        $encontrados = Comercio::query()->habilitados()
            ->with(['micrositio', 'publicidades'])
            ->whereIn('id_comercio', $ids)
            ->get()
            ->keyBy('id_comercio');

        // Se omiten los inexistentes o no habilitados y se conserva el orden de la solicitud.
        $comercios = [];
        foreach ($ids as $id) {
            if ($encontrados->has($id)) {
                $comercios[] = $this->resumen($encontrados->get($id));
            }
        }

        return ['comercios' => $comercios];
    }

    public function obtenerDatosComercioPublico(int $idComercio): array
    {
        $c = Comercio::query()->habilitados()
            ->with(['micrositio', 'banners', 'galeria', 'horarios', 'contactos', 'politicas'])
            ->find($idComercio);

        if ($c === null) {
            throw $this->noEncontrado('El comercio no existe o no está habilitado.');
        }

        $contacto = ['telefono' => null, 'correo' => null, 'direccion' => null];
        $redes = [];
        foreach ($c->contactos as $medio) {
            if (in_array($medio->tipo, ContactoComercio::TIPOS_CONTACTO, true)) {
                $contacto[$medio->tipo] ??= $medio->valor;
            } elseif (in_array($medio->tipo, ContactoComercio::TIPOS_RED_SOCIAL, true)) {
                $redes[] = ['plataforma' => $medio->tipo, 'url' => $medio->valor];
            }
        }

        return [
            'id_comercio' => $c->id_comercio,
            'nombre_comercial' => $c->nombre_comercial,
            'descripcion' => $c->micrositio?->descripcion,
            'logo_url' => $this->url($c->micrositio?->logotipo),
            'banners' => $c->banners->where('activo', true)->sortBy('orden')->map(fn ($b): array => [
                'id_banner' => $b->id_banner,
                'imagen_url' => $this->url($b->ruta),
                'texto_alternativo' => $b->texto_alternativo,
            ])->values()->all(),
            'galeria' => $c->galeria->sortBy('orden')->map(fn ($i): array => [
                'id_imagen' => $i->id_imagen,
                'imagen_url' => $this->url($i->ruta),
                'texto_alternativo' => $i->texto_alternativo,
            ])->values()->all(),
            'horarios' => $c->horarios->sortBy('dia_semana')->map(fn ($h): array => [
                'id_horario' => $h->id_horario,
                'dia_semana' => $h->dia_semana,
                'hora_apertura' => $h->hora_apertura ? substr((string) $h->hora_apertura, 0, 5) : null,
                'hora_cierre' => $h->hora_cierre ? substr((string) $h->hora_cierre, 0, 5) : null,
                'cerrado' => $h->cerrado,
            ])->values()->all(),
            'contacto' => $contacto,
            'redes_sociales' => $redes,
            'politicas' => $c->politicas->map(fn ($p): array => [
                'tipo' => $p->tipo,
                'descripcion' => $p->contenido,
            ])->values()->all(),
            'estado' => $c->estado,
        ];
    }

    public function obtenerComercioActivo(int $idComercio): array
    {
        $c = $this->buscar($idComercio);

        return [
            'id_comercio' => $c->id_comercio,
            'estado' => $c->estado,
            'activo' => $c->estaActivo(),
            'motivo_estado' => $c->motivoEstado(),
        ];
    }

    public function obtenerSedesComercio(int $idComercio): array
    {
        $c = $this->buscar($idComercio);

        // Supuesto del PC3: id_sede = id_ubicacion de M4. La tabla de sedes se agrega
        // cuando M4 lo confirme; mientras tanto la lista va vacía.
        return ['id_comercio' => $c->id_comercio, 'sedes' => []];
    }

    public function obtenerPoliticasEnvio(int $idComercio): array
    {
        $c = $this->buscar($idComercio);
        $politicas = $c->politicas()->get();

        $tarifas = $politicas->where('tipo', 'envio')
            ->flatMap(fn ($p): array => $p->configuracion_envio ?? [])
            ->map(fn (array $t): array => [
                'zona' => (string) ($t['zona'] ?? ''),
                'costo' => (float) ($t['costo'] ?? 0),
                'moneda' => (string) ($t['moneda'] ?? 'CRC'),
            ])->values()->all();

        return [
            'id_comercio' => $c->id_comercio,
            'politicas' => $politicas->map(fn ($p): array => [
                'tipo' => $p->tipo,
                'descripcion' => $p->contenido,
            ])->values()->all(),
            'tarifas' => $tarifas,
        ];
    }

    public function obtenerDatosFiscalesComercio(int $idComercio): array
    {
        $c = $this->buscar($idComercio);
        $comision = ComisionComercio::vigentePara($c->id_comercio);

        return [
            'id_comercio' => $c->id_comercio,
            'cedula_juridica' => $c->cedula_juridica,
            'razon_social' => $c->razon_social,
            'direccion_fiscal' => $c->direccion_fiscal,
            'inscrito_como_contribuyente' => $c->inscrito_como_contribuyente,
            'comision' => $comision === null ? null : [
                'tipo' => $comision->tipo,
                'valor' => (float) $comision->valor,
                'base_calculo' => 'subtotal del subpedido',
                'moneda' => $comision->tipo === ComisionComercio::MONTO_FIJO ? $comision->moneda : null,
            ],
        ];
    }

    public function obtenerComisionSubpedido(int $idSubpedido): array
    {
        $comision = ComisionSubpedido::query()->where('id_subpedido', $idSubpedido)->first();

        if ($comision === null) {
            throw $this->noEncontrado('La comisión del subpedido aún no se ha calculado.');
        }

        return [
            'id_subpedido' => $comision->id_subpedido,
            'id_comercio' => $comision->id_comercio,
            'monto_comision' => (float) $comision->monto,
            'base_utilizada' => (float) $comision->subtotal_base,
            'moneda' => $comision->moneda,
        ];
    }

    // ------------------------------------------------------------------
    // Métodos heredados del borrador del Comité. Se eliminan cuando la
    // interfaz pase a las 8 operaciones del contrato (segundo PR de M2).
    // ------------------------------------------------------------------

    /**
     * @deprecated Usar obtenerDatosComercioPublico (micrositio) u obtenerComercioActivo (estado).
     */
    public function obtenerMicrositio(int $comercioId): array
    {
        $c = $this->buscar($comercioId);

        return [
            'id_comercio' => $c->id_comercio,
            'nombre_comercial' => $c->nombre_comercial,
            'estado' => $c->estado,
            'activo' => $c->estaActivo(),
            'logo_url' => $this->url($c->micrositio?->logotipo),
        ];
    }

    /**
     * @deprecated La comisión no se registra por llamada síncrona: M2 la calcula al
     *             consumir el evento SubpedidoDespachado de M5 (contrato v1.2.1, RN-010)
     *             y M7 la consulta con obtenerComisionSubpedido. No registra nada.
     */
    public function registrarComision(int $pedidoId, float $subtotalPedido): bool
    {
        Log::warning('M2: registrarComision está en desuso; la comisión se registra con el evento SubpedidoDespachado.', [
            'id_pedido' => $pedidoId,
        ]);

        return false;
    }

    // ------------------------------------------------------------------

    private function buscar(int $idComercio): Comercio
    {
        return Comercio::query()->find($idComercio)
            ?? throw $this->noEncontrado('El comercio no existe.');
    }

    // --- CU-0014: revisar solicitudes (Jorge) ---

    /** @return array{pagina: int, por_pagina: int, total: int, solicitudes: list<array<string, mixed>>} */
    public function listarSolicitudes(?string $estado, int $pagina, int $porPagina): array
    {
        $errores = [];
        if ($estado !== null && ! in_array($estado, [SolicitudComercio::PENDIENTE, SolicitudComercio::APROBADA, SolicitudComercio::RECHAZADA], true)) {
            $errores['estado'] = ['El estado de la solicitud no es válido.'];
        }
        if ($pagina < 1) {
            $errores['pagina'] = ['Debe ser un entero mayor o igual a 1.'];
        }
        if ($porPagina < 1 || $porPagina > self::MAXIMO_POR_PAGINA) {
            $errores['por_pagina'] = ['Debe estar entre 1 y '.self::MAXIMO_POR_PAGINA.'.'];
        }
        $this->exigirValidos($errores);

        $consulta = SolicitudComercio::query()->with(['comercio', 'documentos']);
        if ($estado !== null) {
            $consulta->where('estado', $estado);
        }

        $total = (clone $consulta)->count();
        $solicitudes = $consulta->orderByDesc('id_solicitud')->forPage($pagina, $porPagina)->get();

        return [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => $total,
            'solicitudes' => $solicitudes->map(fn (SolicitudComercio $solicitud): array => $this->datosSolicitud($solicitud))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function consultarSolicitud(int $idSolicitud): array
    {
        $solicitud = SolicitudComercio::query()->with(['comercio', 'documentos'])->find($idSolicitud)
            ?? throw $this->noEncontrado('La solicitud no existe.');

        return $this->datosSolicitud($solicitud);
    }

    /** @return array<string, mixed> */
    private function datosSolicitud(SolicitudComercio $solicitud): array
    {
        return $solicitud->only([
            'id_solicitud', 'id_comercio', 'nombre_encargado', 'correo_encargado',
            'telefono_encargado', 'estado', 'requiere_documentos', 'motivo_rechazo',
            'fecha_reenvio', 'created_at', 'updated_at',
        ]) + [
            'comercio' => $solicitud->comercio->only([
                'id_comercio', 'cedula_juridica', 'nombre_comercial', 'razon_social',
                'direccion_fiscal', 'inscrito_como_contribuyente', 'estado', 'created_at', 'updated_at',
            ]),
            'documentos' => $solicitud->documentos->map(fn (DocumentoComercio $documento): array => $documento->only([
                'id_documento_comercio', 'id_comercio', 'tipo', 'fecha_vencimiento',
                'resultado', 'observacion', 'created_at', 'updated_at',
            ]))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function resumen(Comercio $c): array
    {
        return [
            'id_comercio' => $c->id_comercio,
            'nombre_comercial' => $c->nombre_comercial,
            'logo_url' => $this->url($c->micrositio?->logotipo),
            'estado' => $c->estado,
            'destacado' => $c->publicidades->contains(fn (PublicidadComercio $p): bool => $p->estaVigente()),
        ];
    }

    private function url(?string $ruta): ?string
    {
        if ($ruta === null || $ruta === '') {
            return null;
        }

        return str_starts_with($ruta, 'http') ? $ruta : asset($ruta);
    }

    private function noEncontrado(string $mensaje): ErrorNucleoException
    {
        return ErrorNucleoException::paraModulo(self::MODULO, 'ERR-004', 404, $mensaje);
    }

    /** @param  array<string, list<string>>  $errores */
    private function exigirValidos(array $errores): void
    {
        if ($errores !== []) {
            throw ErrorNucleoException::paraModulo(
                self::MODULO,
                'ERR-005',
                422,
                'Revisa los datos marcados (detalle por campo en detalles).',
                $errores,
            );
        }
    }
}
