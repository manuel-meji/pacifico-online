<?php

namespace App\Models\M5_Pedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstadoPedido extends Model
{
    protected $table = 'estados_pedido';

    protected $primaryKey = 'id_estado_pedido';

    protected $fillable = [
        'id_pedido',
        'id_subpedido',
        'id_evento',
        'estado_anterior',
        'estado',
        'usuario_id',
        'motivo',
        'fecha_estado',
    ];

    protected $casts = [
        'id_pedido' => 'integer',
        'id_subpedido' => 'integer',
        'usuario_id' => 'integer',
        'fecha_estado' => 'datetime',
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
