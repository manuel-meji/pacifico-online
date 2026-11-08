<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Política del comercio; las de tipo envio incluyen sus tarifas en configuracion_envio (JSONB).
 *
 * Propietario: M2. Pendiente: aplicar el trait PerteneceAComercio del núcleo
 * cuando ContextoComercio quede registrado como servicio compartido (ver DEF del 4/11).
 */
class PoliticaComercio extends Model
{
    use PerteneceAComercio;

    protected $table = 'politicas_comercio';

    protected $primaryKey = 'id_politica';

    protected $guarded = ['id_politica'];

    protected $casts = ['configuracion_envio' => 'array'];

    // --- Políticas del micrositio
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
