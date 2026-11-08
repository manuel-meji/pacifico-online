<?php

namespace App\Models\M4_Inventario;

use App\Enums\M4_Inventario\EfectoStock;
use App\Enums\M4_Inventario\TipoMovimiento;
use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Hecho inmutable de inventario (tabla movimientos_inventario, RN-M4-003). En
 * PostgreSQL los triggers tr_movimientos_inmutable_* rechazan UPDATE y DELETE;
 * el modelo lo impide también para que el error aparezca antes de la base.
 *
 * @property int $id_movimiento
 * @property int $id_comercio
 * @property int $id_producto
 * @property int $id_variacion
 * @property int $id_ubicacion
 * @property int $id_usuario
 * @property int|null $id_autorizador
 * @property TipoMovimiento $tipo
 * @property EfectoStock $efecto
 * @property int $cantidad
 * @property float $costo_unitario
 * @property string|null $referencia
 * @property string $motivo
 * @property Carbon $fecha_hora
 * @property Carbon|null $fecha_autorizacion
 * @property-read LineaKardex|null $lineaKardex
 */
class MovimientoInventario extends Model
{
    use PerteneceAComercio;

    protected $table = 'movimientos_inventario';

    protected $primaryKey = 'id_movimiento';

    public $timestamps = false;

    protected $guarded = ['id_movimiento'];

    protected function casts(): array
    {
        return [
            'id_comercio' => 'integer',
            'id_producto' => 'integer',
            'id_variacion' => 'integer',
            'id_ubicacion' => 'integer',
            'id_usuario' => 'integer',
            'id_autorizador' => 'integer',
            'tipo' => TipoMovimiento::class,
            'efecto' => EfectoStock::class,
            'cantidad' => 'integer',
            'costo_unitario' => 'float',
            'fecha_hora' => 'datetime',
            'fecha_autorizacion' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de inventario son inmutables (RN-M4-003).'));
        static::deleting(fn () => throw new LogicException('Los movimientos de inventario son inmutables (RN-M4-003).'));
    }

    /** @return HasOne<LineaKardex, $this> */
    public function lineaKardex(): HasOne
    {
        return $this->hasOne(LineaKardex::class, 'id_movimiento', 'id_movimiento');
    }

    public function esFisico(): bool
    {
        return $this->tipo->esFisico();
    }

    /**
     * Esquema Movimiento del contrato. Necesita la línea de kardex para los saldos.
     *
     * @return array<string, mixed>
     */
    public function aContrato(int $idExistencia): array
    {
        $linea = $this->lineaKardex;

        return [
            'id_movimiento' => $this->id_movimiento,
            'id_existencia' => $idExistencia,
            'id_comercio' => $this->id_comercio,
            'id_producto' => $this->id_producto,
            'id_variacion' => $this->id_variacion,
            'id_ubicacion' => $this->id_ubicacion,
            'tipo_movimiento' => $this->tipo->value,
            'efecto_stock' => $this->efecto->contrato(),
            'cantidad' => $this->cantidad,
            'costo_unitario' => $this->esFisico() ? $this->costo_unitario : null,
            'id_usuario' => $this->id_usuario,
            'referencia_tipo' => $this->tipo->referenciaTipo(),
            'referencia_id' => $this->referencia,
            'justificacion' => $this->motivo,
            'fecha_movimiento' => $this->fecha_hora->toIso8601String(),
            'id_autorizador' => $this->id_autorizador,
            'fecha_autorizacion' => $this->fecha_autorizacion?->toIso8601String(),
            'saldo_reservado' => $linea?->saldo_reservado,
            'saldo_disponible' => $linea?->saldoDisponible(),
        ];
    }
}
