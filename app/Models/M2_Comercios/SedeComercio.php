<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sede física del comercio (tienda o bodega). Su id_sede es el id_ubicacion de M4
 * (contrato M2 v1.2.1, SedeHabilitada). La consulta obtenerSedesComercio la usa M4
 * para validar dónde puede reservar inventario.
 */
class SedeComercio extends Model
{
    protected $table = 'sedes_comercio';

    protected $primaryKey = 'id_sede';

    protected $guarded = ['id_sede'];

    protected $casts = ['habilitada' => 'boolean'];

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }
}
