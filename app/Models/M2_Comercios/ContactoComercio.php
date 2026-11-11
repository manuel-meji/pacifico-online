<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Medio de contacto o red social del comercio (CU-0007).
 *
 * Propietario: M2. Pendiente: aplicar el trait PerteneceAComercio del núcleo
 * cuando ContextoComercio quede registrado como servicio compartido (ver DEF del 4/11).
 */
class ContactoComercio extends Model
{
    use PerteneceAComercio;

    protected $table = 'contacto_comercio';

    protected $primaryKey = 'id_contacto';

    protected $guarded = ['id_contacto'];

    protected $casts = [];

    public const TIPOS_CONTACTO = ['telefono', 'correo', 'direccion'];

    public const TIPOS_RED_SOCIAL = ['facebook', 'instagram', 'whatsapp', 'tiktok', 'sitio_web'];

    // --- CU-0007: administrar contactos y redes sociales
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
