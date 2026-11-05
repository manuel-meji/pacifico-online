<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Banner del micrositio (CU-0004).
 *
 * Propietario: M2. Pendiente: aplicar el trait PerteneceAComercio del núcleo
 * cuando ContextoComercio quede registrado como servicio compartido (ver DEF del 4/11).
 */
class BannerComercio extends Model
{
    protected $table = 'banners_comercio';

    protected $primaryKey = 'id_banner';

    protected $guarded = ['id_banner'];

    protected $casts = ['orden' => 'integer', 'activo' => 'boolean'];

    // --- CU-0004: administrar banner
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
