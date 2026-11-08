<?php

namespace App\Models\M4_Inventario;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Línea inmutable del kardex (tabla kardex_inventario), una por movimiento.
 * monto_total es el importe del movimiento con redondeo contable (lo usa M7);
 * costo_promedio es el promedio vigente después del movimiento.
 *
 * @property int $id_kardex
 * @property int $id_comercio
 * @property int $id_movimiento
 * @property int $saldo_cantidad
 * @property int $saldo_reservado
 * @property float $monto_total
 * @property float $costo_promedio
 * @property-read MovimientoInventario $movimiento
 */
class LineaKardex extends Model
{
    use PerteneceAComercio;

    public const METODO_VALORIZACION = 'promedio_ponderado';

    protected $table = 'kardex_inventario';

    protected $primaryKey = 'id_kardex';

    public $timestamps = false;

    protected $guarded = ['id_kardex'];

    protected function casts(): array
    {
        return [
            'id_comercio' => 'integer',
            'id_movimiento' => 'integer',
            'saldo_cantidad' => 'integer',
            'saldo_reservado' => 'integer',
            'monto_total' => 'float',
            'costo_promedio' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las líneas del kardex son inmutables (RN-M4-003).'));
        static::deleting(fn () => throw new LogicException('Las líneas del kardex son inmutables (RN-M4-003).'));
    }

    /** @return BelongsTo<MovimientoInventario, $this> */
    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'id_movimiento', 'id_movimiento');
    }

    public function saldoDisponible(): int
    {
        return $this->saldo_cantidad - $this->saldo_reservado;
    }

    public function saldoValorizado(): float
    {
        return round($this->saldo_cantidad * $this->costo_promedio, 2);
    }

    /**
     * Esquema LineaKardex del contrato.
     *
     * @return array<string, mixed>
     */
    public function aContrato(): array
    {
        $m = $this->movimiento;
        $fisico = $m->esFisico();

        return [
            'id_kardex' => $this->id_kardex,
            'id_movimiento' => $this->id_movimiento,
            'id_comercio' => $this->id_comercio,
            'id_producto' => $m->id_producto,
            'id_variacion' => $m->id_variacion,
            'id_ubicacion' => $m->id_ubicacion,
            'tipo_movimiento' => $m->tipo->value,
            'efecto_stock' => $m->efecto->contrato(),
            'cantidad' => $m->cantidad,
            'metodo_valorizacion' => self::METODO_VALORIZACION,
            'costo_unitario' => $fisico ? $m->costo_unitario : null,
            'monto_total' => $fisico ? $this->monto_total : null,
            'saldo_cantidad' => $this->saldo_cantidad,
            'saldo_valorizado' => $this->saldoValorizado(),
            'referencia_tipo' => $m->tipo->referenciaTipo(),
            'referencia_id' => $m->referencia,
            'fecha_movimiento' => $m->fecha_hora->toIso8601String(),
            'fecha_registro' => $m->fecha_hora->toIso8601String(),
            'id_usuario' => $m->id_usuario,
            'justificacion' => $m->motivo,
            'id_autorizador' => $m->id_autorizador,
            'fecha_autorizacion' => $m->fecha_autorizacion?->toIso8601String(),
            'saldo_reservado' => $this->saldo_reservado,
            'saldo_disponible' => $this->saldoDisponible(),
        ];
    }
}
