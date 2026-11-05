<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comisión calculada para un subpedido de M5 (RN-010). id_subpedido es una
 * referencia lógica (sin llave foránea, decisión 1.9 del Comité) y es única:
 * garantiza la idempotencia del listener RegistrarComision (Ds-03).
 */
class ComisionSubpedido extends Model
{
    protected $table = 'comisiones_subpedido';

    protected $primaryKey = 'id_comision_subpedido';

    protected $guarded = ['id_comision_subpedido'];

    protected $casts = ['subtotal_base' => 'decimal:2', 'monto' => 'decimal:2'];

    public static function existePorSubpedido(int $idSubpedido): bool
    {
        return static::query()->where('id_subpedido', $idSubpedido)->exists();
    }

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }
}
