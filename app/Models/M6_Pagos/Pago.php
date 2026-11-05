<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Intento de pago de un pedido (tabla pagos). Privado de M6: ningún otro módulo
 * lo importa; lo consultan por el contrato (PagosServiceInterface / API).
 *
 * id_pedido e id_cliente son referencias a M5 y M1: se guardan como número y
 * se leen por contrato, nunca con JOIN.
 */
class Pago extends Model
{
    protected $table = 'pagos';

    protected $primaryKey = 'id_pago';

    public $timestamps = false;

    protected $fillable = [
        'id_pedido',
        'id_cliente',
        'id_medio_pago',
        'id_estado_pago',
        'monto',
        'clave_idempotencia',
        'referencia_proveedor',
        'fecha_creacion',
        'fecha_confirmacion',
    ];

    protected function casts(): array
    {
        return [
            'id_pedido' => 'integer',
            'id_cliente' => 'integer',
            'monto' => 'decimal:2',
            'fecha_creacion' => 'datetime',
            'fecha_confirmacion' => 'datetime',
        ];
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoPago::class, 'id_estado_pago', 'id_estado_pago');
    }

    public function medio(): BelongsTo
    {
        return $this->belongsTo(MedioPago::class, 'id_medio_pago', 'id_medio_pago');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(NotificacionPago::class, 'id_pago', 'id_pago');
    }

    public function codigoEstado(): string
    {
        return $this->estado->codigo;
    }

    public function estaPendiente(): bool
    {
        return $this->codigoEstado() === EstadoPago::PENDIENTE;
    }
}
