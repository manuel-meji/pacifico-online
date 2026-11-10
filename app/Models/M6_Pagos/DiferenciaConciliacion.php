<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Diferencia encontrada al conciliar (tabla diferencias_conciliacion). Privada de M6.
 * monto_diferencia = monto en el sistema − monto reportado por la pasarela (nunca 0).
 */
class DiferenciaConciliacion extends Model
{
    protected $table = 'diferencias_conciliacion';

    protected $primaryKey = 'id_diferencia';

    public $timestamps = false;

    protected $fillable = [
        'id_conciliacion',
        'id_pago',
        'id_tipo_diferencia',
        'id_estado_revision_diferencia',
        'monto_diferencia',
    ];

    protected function casts(): array
    {
        return [
            'id_conciliacion' => 'integer',
            'id_pago' => 'integer',
            'monto_diferencia' => 'decimal:2',
        ];
    }

    public function conciliacion(): BelongsTo
    {
        return $this->belongsTo(Conciliacion::class, 'id_conciliacion', 'id_conciliacion');
    }
}
