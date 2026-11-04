<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento de la solicitud, guardado cifrado (RN-002); se verifica en CU-0022.
 *
 * Propietario: M2. Pendiente: aplicar el trait PerteneceAComercio del núcleo
 * cuando ContextoComercio quede registrado como servicio compartido (ver DEF del 4/11).
 */
class DocumentoComercio extends Model
{
    protected $table = 'documentos_comercio';

    protected $primaryKey = 'id_documento_comercio';

    protected $guarded = ['id_documento_comercio'];

    protected $casts = ['fecha_vencimiento' => 'date'];

    /** RN-002: el documento fue validado y sigue vigente. */
    public function esValido(): bool
    {
        return $this->resultado === 'valido'
            && ($this->fecha_vencimiento === null || $this->fecha_vencimiento->endOfDay()->isFuture());
    }

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }
}
