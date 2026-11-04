<?php

namespace App\Models\M5_Pedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancelacionPedido extends Model
{
    protected $table = 'cancelaciones_pedido';

    protected $primaryKey = 'id_cancelacion';

    protected $fillable = [
        'id_pedido',
        'id_subpedido',
        'motivo',
        'requiere_reembolso',
        'fecha_cancelacion',
    ];

    protected $casts = [
        'id_pedido' => 'integer',
        'id_subpedido' => 'integer',
        'requiere_reembolso' => 'boolean',
        'fecha_cancelacion' => 'datetime',
    ];

    public function pedidoMaestro(): BelongsTo
    {
        return $this->belongsTo(PedidoMaestro::class, 'id_pedido', 'id_pedido');
    }

    public function subpedido(): BelongsTo
    {
        return $this->belongsTo(Subpedido::class, 'id_subpedido', 'id_subpedido');
    }
}
