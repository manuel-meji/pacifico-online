<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reversión de comisión por una devolución aprobada en M5 (RF-021). id_devolucion es una
 * referencia lógica (sin llave foránea) y es única: garantiza la idempotencia del listener.
 */
class ComisionReversion extends Model
{
    protected $table = 'comisiones_reversion';

    protected $primaryKey = 'id_comision_reversion';

    protected $guarded = ['id_comision_reversion'];

    protected $casts = ['subtotal_devuelto' => 'decimal:2', 'monto_revertido' => 'decimal:2'];

    public function comision(): BelongsTo
    {
        return $this->belongsTo(ComisionSubpedido::class, 'id_comision_subpedido', 'id_comision_subpedido');
    }
}
