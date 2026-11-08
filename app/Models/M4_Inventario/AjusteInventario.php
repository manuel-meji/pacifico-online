<?php

namespace App\Models\M4_Inventario;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Conteo físico (tabla ajustes_inventario). Sin diferencia no tiene movimiento;
 * con diferencia apunta al movimiento de ajuste (RN-M4-011).
 *
 * @property int $id_ajuste
 * @property int $id_comercio
 * @property int $id_existencia
 * @property int $id_motivo_ajuste
 * @property int|null $id_movimiento
 * @property int $id_usuario
 * @property int $cantidad_contada
 * @property int $cantidad_sistema
 * @property string $justificacion
 * @property Carbon $created_at
 * @property-read MotivoAjuste $motivo
 * @property-read MovimientoInventario|null $movimiento
 */
class AjusteInventario extends Model
{
    use PerteneceAComercio;

    protected $table = 'ajustes_inventario';

    protected $primaryKey = 'id_ajuste';

    public $timestamps = false;

    protected $guarded = ['id_ajuste'];

    protected function casts(): array
    {
        return [
            'id_comercio' => 'integer',
            'id_existencia' => 'integer',
            'id_motivo_ajuste' => 'integer',
            'id_movimiento' => 'integer',
            'id_usuario' => 'integer',
            'cantidad_contada' => 'integer',
            'cantidad_sistema' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MotivoAjuste, $this> */
    public function motivo(): BelongsTo
    {
        return $this->belongsTo(MotivoAjuste::class, 'id_motivo_ajuste', 'id_motivo_ajuste');
    }

    /** @return BelongsTo<MovimientoInventario, $this> */
    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'id_movimiento', 'id_movimiento');
    }

    public function diferencia(int $cantidadSistema): int
    {
        return $this->cantidad_contada - $cantidadSistema;
    }
}
