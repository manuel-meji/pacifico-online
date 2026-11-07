<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suspensión de un comercio con su motivo obligatorio (RN-005). Queda abierta
 * mientras fecha_reactivacion sea null (CU-0017, CU-0018). id_usuario es una
 * referencia lógica al núcleo.
 */
class SuspensionComercio extends Model
{
    protected $table = 'suspensiones_comercio';

    protected $primaryKey = 'id_suspension';

    protected $guarded = ['id_suspension'];

    protected $casts = ['fecha_suspension' => 'datetime', 'fecha_reactivacion' => 'datetime'];

    public function estaAbierta(): bool
    {
        return $this->fecha_reactivacion === null;
    }

    /** CU-0018: cierra la suspensión al reactivar el comercio. */
    public function cerrar(): void
    {
        $this->fecha_reactivacion = now();
        $this->save();
    }

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }
}
