<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración de comisión del comercio (RN-006, RN-007). Cada cambio crea una
 * fila nueva con su vigente_desde, para conservar el historial (RN-015, RN-017).
 */
class ComisionComercio extends Model
{
    public const PORCENTAJE = 'porcentaje';

    public const MONTO_FIJO = 'monto_fijo';

    protected $table = 'comisiones_comercio';

    protected $primaryKey = 'id_comision_comercio';

    protected $guarded = ['id_comision_comercio'];

    protected $casts = ['valor' => 'decimal:2', 'vigente_desde' => 'datetime'];

    /** La configuración más reciente que ya estaba vigente en la fecha indicada (ahora, si se omite). */
    public static function vigentePara(int $idComercio, ?DateTimeInterface $fecha = null): ?self
    {
        return static::query()
            ->where('id_comercio', $idComercio)
            ->where('vigente_desde', '<=', $fecha ?? now())
            ->orderByDesc('vigente_desde')
            ->first();
    }

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }

    public function membresia(): BelongsTo
    {
        return $this->belongsTo(MembresiaComercio::class, 'id_membresia_comercio', 'id_membresia_comercio');
    }
}
