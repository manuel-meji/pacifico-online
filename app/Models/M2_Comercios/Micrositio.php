<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Micrositio del comercio: descripción (información empresarial) y logotipo (CU-0001 a CU-0003).
 *
 * Propietario: M2. Pendiente: aplicar el trait PerteneceAComercio del núcleo
 * cuando ContextoComercio quede registrado como servicio compartido (ver DEF del 4/11).
 */
class Micrositio extends Model
{
    protected $table = 'micrositios';

    protected $primaryKey = 'id_micrositio';

    protected $guarded = ['id_micrositio'];

    protected $casts = [];

    // --- CU-0003: administrar logotipo
    public function cambiarLogotipo(?string $ruta): void
    {
        $this->logotipo = $ruta;
        $this->save();
    }

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }
}
