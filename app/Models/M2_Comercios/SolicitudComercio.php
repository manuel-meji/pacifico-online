<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Solicitud de incorporación (CU-0012, CU-0013) que revisa el Encargado de la
 * plataforma (CU-0014 a CU-0016, CU-0022). El rechazo exige motivo (RN-016).
 */
class SolicitudComercio extends Model
{
    public const PENDIENTE = 'Pendiente';

    public const APROBADA = 'Aprobada';

    public const RECHAZADA = 'Rechazada';

    protected $table = 'solicitudes_comercio';

    protected $primaryKey = 'id_solicitud';

    protected $guarded = ['id_solicitud'];

    protected $casts = ['requiere_documentos' => 'boolean', 'fecha_reenvio' => 'datetime'];

    public function estaPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    public function rechazar(string $motivo): void
    {
        $this->estado = self::RECHAZADA;
        $this->motivo_rechazo = $motivo;
        $this->save();
    }

    public function aprobar(): void
    {
        $this->estado = self::APROBADA;
        $this->save();
    }

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoComercio::class, 'id_comercio', 'id_comercio');
    }
}
