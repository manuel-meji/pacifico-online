<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Horario de atención por día (1 = lunes ... 7 = domingo) (CU-0006).
 *
 * Propietario: M2. Pendiente: aplicar el trait PerteneceAComercio del núcleo
 * cuando ContextoComercio quede registrado como servicio compartido (ver DEF del 4/11).
 */
class HorarioComercio extends Model
{
    protected $table = 'horarios_comercio';

    protected $primaryKey = 'id_horario';

    protected $guarded = ['id_horario'];

    protected $casts = ['dia_semana' => 'integer', 'cerrado' => 'boolean'];

    // --- CU-0006: administrar horarios 
    public function actualizar(array $datos): void
    {
        $this->fill($datos);
        $this->save();
    }

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }
}
