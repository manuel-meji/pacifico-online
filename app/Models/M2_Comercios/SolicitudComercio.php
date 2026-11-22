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

    protected $casts = [
        'requiere_documentos' => 'boolean', 'fecha_reenvio' => 'datetime', 'cuenta_creada' => 'boolean',
        'fecha_revision' => 'datetime',
    ];

    public function estaPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    /** El motivo también queda como último motivo de rechazo, que se conserva después de corregir. */
    public function rechazar(string $motivo, ?int $idRevisor = null, ?string $nombreRevisor = null): void
    {
        $this->estado = self::RECHAZADA;
        $this->motivo_rechazo = $motivo;
        $this->ultimo_motivo_rechazo = $motivo;
        $this->registrarRevision($idRevisor, $nombreRevisor);
        $this->save();
    }

    public function aprobar(?int $idRevisor = null, ?string $nombreRevisor = null): void
    {
        $this->estado = self::APROBADA;
        $this->registrarRevision($idRevisor, $nombreRevisor);
        $this->save();
    }

    private function registrarRevision(?int $idRevisor, ?string $nombreRevisor): void
    {
        $this->fecha_revision = now();
        $this->revisado_por = $idRevisor;
        $this->nombre_revisor = $nombreRevisor;
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
