<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membresía del comercio; puede fijar una comisión preferencial (RN-008).
 *
 * Propietario: M2. Pendiente: aplicar el trait PerteneceAComercio del núcleo
 * cuando ContextoComercio quede registrado como servicio compartido (ver DEF del 4/11).
 */
class MembresiaComercio extends Model
{
    protected $table = 'membresias_comercio';

    protected $primaryKey = 'id_membresia_comercio';

    protected $guarded = ['id_membresia_comercio'];

    protected $casts = ['comision_preferencial' => 'decimal:2', 'inicio' => 'date', 'fin' => 'date', 'activa' => 'boolean'];

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }
}
