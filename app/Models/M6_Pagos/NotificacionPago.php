<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notificación (webhook) recibida de la pasarela, SINPE o PayPal.
 * La columna referencia_proveedor es UNIQUE: es la base de la idempotencia (RN-M6-02, ADR-003).
 */
class NotificacionPago extends Model
{
    protected $table = 'notificaciones_pago';

    protected $primaryKey = 'id_notificacion_pago';

    public $timestamps = false;

    protected $fillable = [
        'id_pago',
        'id_medio_pago',
        'id_estado_notificacion_pago',
        'referencia_proveedor',
        'fecha_recepcion',
    ];

    protected function casts(): array
    {
        return ['fecha_recepcion' => 'datetime'];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'id_pago', 'id_pago');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoNotificacionPago::class, 'id_estado_notificacion_pago', 'id_estado_notificacion_pago');
    }
}
