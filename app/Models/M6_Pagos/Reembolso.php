<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reembolso de un subpedido (tabla reembolsos). Privado de M6.
 *
 * Decisión del subgrupo: un reembolso por subpedido, cada uno con su nota de
 * crédito. id_subpedido es una referencia a M5: se guarda como número y se lee
 * por contrato, nunca con JOIN.
 */
class Reembolso extends Model
{
    protected $table = 'reembolsos';

    protected $primaryKey = 'id_reembolso';

    public $timestamps = false;

    protected $fillable = [
        'id_pago',
        'id_subpedido',
        'id_nota_credito',
        'id_estado_reembolso',
        'monto',
        'motivo',
        'fecha_solicitud',
        'fecha_procesado',
    ];

    protected function casts(): array
    {
        return [
            'id_pago' => 'integer',
            'id_subpedido' => 'integer',
            'id_nota_credito' => 'integer',
            'monto' => 'decimal:2',
            'fecha_solicitud' => 'datetime',
            'fecha_procesado' => 'datetime',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'id_pago', 'id_pago');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoReembolso::class, 'id_estado_reembolso', 'id_estado_reembolso');
    }

    public function notaCredito(): BelongsTo
    {
        return $this->belongsTo(NotaCredito::class, 'id_nota_credito', 'id_nota_credito');
    }

    public function codigoEstado(): string
    {
        return $this->estado->codigo;
    }
}
