<?php

namespace App\Services\M4_Inventario;

use App\Enums\M4_Inventario\EfectoStock;
use App\Enums\M4_Inventario\EstadoReservaCodigo;
use App\Enums\M4_Inventario\TipoMovimiento;
use App\Models\M4_Inventario\Existencia;
use App\Models\M4_Inventario\LineaKardex;
use App\Models\M4_Inventario\MovimientoInventario;
use App\Models\M4_Inventario\Reserva;
use App\Services\M4_Inventario\Dominio\Valoracion\MetodoValoracion;
use App\Services\M4_Inventario\DTO\DatosMovimiento;
use App\Services\M4_Inventario\DTO\FiltroKardex;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\M4_Inventario\Repositorios\MovimientoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Kardex valorizado (contexto del patrón Strategy, figura 4.2a del PC3).
 *
 * Cada movimiento se asienta con su línea de kardex inmutable (RN-M4-003):
 * saldos físico y reservado después del movimiento, importe con redondeo
 * contable y costo promedio vigente. Los movimientos de reserva no cambian el
 * físico ni tienen costo: solo trazan la disponibilidad.
 */
class KardexService
{
    public function __construct(
        private readonly MetodoValoracion $estrategia,
        private readonly MovimientoRepositoryInterface $movimientos,
    ) {}

    /**
     * Asienta el movimiento y su línea. La existencia ya tiene los saldos posteriores.
     */
    public function registrar(Existencia $existencia, DatosMovimiento $datos): MovimientoInventario
    {
        $fisico = $datos->tipo->esFisico();
        $costo = $fisico ? round($datos->costoUnitario, 4) : 0.0;

        $movimiento = new MovimientoInventario([
            'id_comercio' => $existencia->id_comercio,
            'id_producto' => $existencia->id_producto,
            'id_variacion' => $existencia->id_variacion,
            'id_ubicacion' => $existencia->id_ubicacion,
            'id_usuario' => $datos->idUsuario,
            'id_autorizador' => $datos->idAutorizador,
            'tipo' => $datos->tipo,
            'efecto' => $fisico ? $datos->efecto : EfectoStock::Ninguno,
            'cantidad' => $datos->cantidad,
            'costo_unitario' => $costo,
            'referencia' => $datos->referencia,
            'motivo' => $datos->motivo,
            'fecha_hora' => Carbon::now(),
            'fecha_autorizacion' => $datos->fechaAutorizacion,
        ]);

        $linea = new LineaKardex([
            'saldo_cantidad' => $existencia->cantidad_fisica,
            'saldo_reservado' => $existencia->cantidad_reservada,
            'monto_total' => $fisico ? round($datos->cantidad * $costo, 2) : 0,
            'costo_promedio' => $existencia->costo_promedio,
        ]);

        $this->movimientos->registrar($movimiento, $linea);

        return $movimiento;
    }

    public function costoSalida(Existencia $existencia, int $cantidad): float
    {
        return $this->estrategia->costoDeSalida($existencia, $cantidad);
    }

    public function costoTrasEntrada(Existencia $existencia, int $cantidad, float $costoUnitario): float
    {
        return $this->estrategia->costoTrasEntrada($existencia, $cantidad, $costoUnitario);
    }

    /**
     * Kardex de una variación, del movimiento más reciente al más antiguo.
     *
     * @return array{datos: list<array<string, mixed>>, paginacion: array<string, int>}
     */
    public function consultar(FiltroKardex $filtro): array
    {
        $consulta = $this->lineas($filtro)
            ->orderByDesc('m.fecha_hora')
            ->orderByDesc('kardex_inventario.id_kardex');

        return $this->pagina($consulta->paginate($filtro->porPagina, ['kardex_inventario.*'], 'pagina', $filtro->pagina));
    }

    /**
     * Kardex valorizado del periodo, en orden cronológico (para M7 y reportes).
     *
     * @return array{datos: list<array<string, mixed>>, paginacion: array<string, int>}
     */
    public function valorizado(FiltroKardex $filtro): array
    {
        $consulta = $this->lineas($filtro)
            ->orderBy('m.fecha_hora')
            ->orderBy('kardex_inventario.id_kardex');

        return $this->pagina($consulta->paginate($filtro->porPagina, ['kardex_inventario.*'], 'pagina', $filtro->pagina));
    }

    /**
     * Costo de los productos vendidos de un subpedido: suma las salidas confirmadas
     * de sus reservas consumidas, una vez cada una, con el costo registrado al
     * despachar (no se revaloriza). Si alguna reserva del subpedido sigue activa o
     * confirmada, no se entrega un resultado parcial (ERR-M4-019).
     *
     * @return array{id_subpedido: int, id_comercio: int, variaciones: list<array{id_variacion: int, cantidad: int, costo_unitario: float, costo_total: float}>, costo_total: float}
     */
    public function costosProductosVendidos(int $idSubpedido, bool $conSesionDeComercio): array
    {
        $reservas = Reserva::query()->where('id_subpedido', $idSubpedido)->get();

        if ($reservas->isEmpty()) {
            throw $conSesionDeComercio
                ? ErrorInventarioException::recursoNoAccesible()
                : ErrorInventarioException::subpedidoSinRegistros();
        }

        if ($reservas->contains(fn (Reserva $r): bool => $r->estadoCodigo()->comprometeUnidades())) {
            throw ErrorInventarioException::salidasPendientes();
        }

        $consumidas = $reservas->filter(fn (Reserva $r): bool => $r->estadoCodigo() === EstadoReservaCodigo::Consumida);

        if ($consumidas->isEmpty()) {
            throw ErrorInventarioException::subpedidoSinRegistros();
        }

        $salidas = MovimientoInventario::query()
            ->with('lineaKardex')
            ->where('id_comercio', $consumidas->first()->id_comercio)
            ->where('tipo', TipoMovimiento::Salida->value)
            ->whereIn('referencia', $consumidas->pluck('id_reserva'))
            ->orderBy('id_movimiento')
            ->get();

        if ($salidas->count() < $consumidas->count()) {
            throw ErrorInventarioException::salidasPendientes();
        }

        $variaciones = $salidas->groupBy('id_variacion')->map(function ($grupo, $idVariacion): array {
            $cantidad = (int) $grupo->sum('cantidad');
            $costoTotal = round((float) $grupo->sum(fn (MovimientoInventario $m): float => $m->lineaKardex->monto_total), 2);

            return [
                'id_variacion' => (int) $idVariacion,
                'cantidad' => $cantidad,
                'costo_unitario' => round($costoTotal / $cantidad, 4),
                'costo_total' => $costoTotal,
            ];
        })->values()->all();

        return [
            'id_subpedido' => $idSubpedido,
            'id_comercio' => $consumidas->first()->id_comercio,
            'variaciones' => $variaciones,
            'costo_total' => round(array_sum(array_column($variaciones, 'costo_total')), 2),
        ];
    }

    /**
     * @return Builder<LineaKardex>
     */
    private function lineas(FiltroKardex $filtro): Builder
    {
        return LineaKardex::query()
            ->with('movimiento')
            ->join('movimientos_inventario as m', 'm.id_movimiento', '=', 'kardex_inventario.id_movimiento')
            ->where('m.id_comercio', $filtro->idComercio)
            ->when($filtro->idVariacion !== null, fn ($q) => $q->where('m.id_variacion', $filtro->idVariacion))
            ->when($filtro->idUbicacion !== null, fn ($q) => $q->where('m.id_ubicacion', $filtro->idUbicacion))
            ->when($filtro->tipoMovimiento !== null, fn ($q) => $q->where('m.tipo', $filtro->tipoMovimiento))
            ->when($filtro->desde !== null, fn ($q) => $q->where('m.fecha_hora', '>=', $filtro->desde))
            ->when($filtro->hasta !== null, fn ($q) => $q->where('m.fecha_hora', '<=', $filtro->hasta));
    }

    /**
     * @param  LengthAwarePaginator<int, LineaKardex>  $pagina
     * @return array{datos: list<array<string, mixed>>, paginacion: array<string, int>}
     */
    private function pagina(LengthAwarePaginator $pagina): array
    {
        return [
            'datos' => array_map(fn (LineaKardex $linea): array => $linea->aContrato(), $pagina->items()),
            'paginacion' => [
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
                'total_paginas' => max(1, $pagina->lastPage()),
            ],
        ];
    }
}
