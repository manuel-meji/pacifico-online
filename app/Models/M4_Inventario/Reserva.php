<?php

namespace App\Models\M4_Inventario;

use App\Enums\M4_Inventario\EstadoReservaCodigo;
use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reserva temporal de unidades de una existencia (tabla reservas). La clave es un
 * UUID que genera M4. El trigger tr_reservas_transicion solo permite 1→2, 1→4,
 * 1→5, 2→3 y 2→4; cada cambio queda en historial (jsonb).
 *
 * @property string $id_reserva
 * @property int $id_comercio
 * @property int $id_existencia
 * @property EstadoReservaCodigo $id_estado_reserva
 * @property int|null $id_pedido
 * @property int|null $id_subpedido
 * @property int $cantidad
 * @property Carbon $fecha_creacion
 * @property Carbon $fecha_vencimiento
 * @property string $clave_idempotencia
 * @property list<array{estado: string, fecha: string, motivo: string}> $historial
 * @property-read Existencia $existencia
 */
class Reserva extends Model
{
    use PerteneceAComercio;

    protected $table = 'reservas';

    protected $primaryKey = 'id_reserva';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id_comercio' => 'integer',
            'id_existencia' => 'integer',
            'id_estado_reserva' => EstadoReservaCodigo::class,
            'id_pedido' => 'integer',
            'id_subpedido' => 'integer',
            'cantidad' => 'integer',
            'fecha_creacion' => 'datetime',
            'fecha_vencimiento' => 'datetime',
            'historial' => 'array',
        ];
    }

    /** @return BelongsTo<Existencia, $this> */
    public function existencia(): BelongsTo
    {
        return $this->belongsTo(Existencia::class, 'id_existencia', 'id_existencia');
    }

    public function estadoCodigo(): EstadoReservaCodigo
    {
        return $this->id_estado_reserva;
    }

    /**
     * Esquema Reserva del contrato.
     *
     * @return array<string, mixed>
     */
    public function aContrato(): array
    {
        $estado = $this->estadoCodigo();
        $historial = $this->historial ?? [];
        $ultimo = $historial === [] ? null : $historial[array_key_last($historial)];
        $cierre = $estado->esCerrada() ? $ultimo : null;

        return [
            'id_reserva' => $this->id_reserva,
            'id_comercio' => $this->id_comercio,
            'id_existencia' => $this->id_existencia,
            'id_variacion' => (int) $this->existencia->id_variacion,
            'id_ubicacion' => (int) $this->existencia->id_ubicacion,
            'cantidad' => $this->cantidad,
            'estado_reserva' => $estado->codigo(),
            'id_pedido' => $this->id_pedido,
            'id_subpedido' => $this->id_subpedido,
            'fecha_creacion' => $this->fecha_creacion->toIso8601String(),
            'fecha_expiracion' => $this->fecha_vencimiento->toIso8601String(),
            'fecha_resolucion' => count($historial) > 1 ? $ultimo['fecha'] : null,
            'motivo_cierre' => $cierre['motivo'] ?? null,
        ];
    }
}
