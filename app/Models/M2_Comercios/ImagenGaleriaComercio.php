<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imagen de la galería del micrositio (CU-0005).
 *
 * Propietario: M2. Pendiente: aplicar el trait PerteneceAComercio del núcleo
 * cuando ContextoComercio quede registrado como servicio compartido (ver DEF del 4/11).
 */
class ImagenGaleriaComercio extends Model
{
    use PerteneceAComercio;

    protected $table = 'galeria_comercio';

    protected $primaryKey = 'id_imagen';

    protected $guarded = ['id_imagen'];

    protected $casts = ['orden' => 'integer'];

    // --- CU-0005: administrar galería
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
