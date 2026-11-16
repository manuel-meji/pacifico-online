<?php

namespace App\Models\M5_Pedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea (y cantidad) de un subpedido incluida en una devolución.
 */
class DevolucionLinea extends Model
{
    protected $table = 'devoluciones_lineas';

    protected $primaryKey = 'id_devolucion_linea';

    protected $fillable = [
        'id_devolucion',
        'id_linea',
        'id_variacion',
        'id_reserva',
        'cantidad',
        'monto',
    ];

    protected $casts = [
        'id_devolucion' => 'integer',
        'id_linea' => 'integer',
        'id_variacion' => 'integer',
        'cantidad' => 'integer',
        'monto' => 'decimal:2',
    ];

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(DevolucionSubpedido::class, 'id_devolucion', 'id_devolucion');
    }
}
