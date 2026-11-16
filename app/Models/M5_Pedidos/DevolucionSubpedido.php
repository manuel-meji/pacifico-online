<?php

namespace App\Models\M5_Pedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Solicitud de devolución (total o parcial) de un subpedido entregado.
 * RF-019 (cliente solicita), RF-020 (comercio revisa) y RF-021 (procesamiento al aprobar).
 *
 * No usa el scope BelongsToComercio a propósito: el cliente y el comercio consultan la misma
 * devolución y la autorización se valida de forma explícita en DevolucionesService.
 */
class DevolucionSubpedido extends Model
{
    public const ESTADO_SOLICITADA = 'solicitada';

    public const ESTADO_APROBADA = 'aprobada';

    public const ESTADO_RECHAZADA = 'rechazada';

    public const TIPO_TOTAL = 'total';

    public const TIPO_PARCIAL = 'parcial';

    public const REEMBOLSO_PENDIENTE = 'pendiente';

    public const REEMBOLSO_PROCESADO = 'procesado';

    public const REEMBOLSO_ERROR = 'error';

    protected $table = 'devoluciones_subpedido';

    protected $primaryKey = 'id_devolucion';

    protected $fillable = [
        'id_pedido',
        'id_subpedido',
        'id_comercio',
        'id_cliente',
        'tipo',
        'estado',
        'motivo',
        'motivo_rechazo',
        'monto_solicitado',
        'monto_reembolsado',
        'id_reembolso_m6',
        'estado_reembolso',
        'error_reembolso',
        'id_usuario_revision',
        'fecha_solicitud',
        'fecha_revision',
    ];

    protected $casts = [
        'id_pedido' => 'integer',
        'id_subpedido' => 'integer',
        'id_comercio' => 'integer',
        'id_cliente' => 'integer',
        'monto_solicitado' => 'decimal:2',
        'monto_reembolsado' => 'decimal:2',
        'id_reembolso_m6' => 'integer',
        'id_usuario_revision' => 'integer',
        'fecha_solicitud' => 'datetime',
        'fecha_revision' => 'datetime',
    ];

    public function lineas(): HasMany
    {
        return $this->hasMany(DevolucionLinea::class, 'id_devolucion', 'id_devolucion');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(DevolucionEvidencia::class, 'id_devolucion', 'id_devolucion');
    }

    public function subpedido(): BelongsTo
    {
        return $this->belongsTo(Subpedido::class, 'id_subpedido', 'id_subpedido')->withoutGlobalScopes();
    }
}
