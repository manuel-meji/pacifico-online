<?php

namespace App\Models\M4_Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Evento de otro módulo que M4 ya aplicó (tabla eventos_procesados_inventario).
 * Los listeners lo registran en la misma transacción que su efecto, así que un
 * evento reenviado con el mismo id_evento no se aplica dos veces.
 *
 * @property string $id_evento
 * @property string $nombre_evento
 * @property Carbon $fecha_procesado
 */
class EventoProcesadoInventario extends Model
{
    protected $table = 'eventos_procesados_inventario';

    protected $primaryKey = 'id_evento';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fecha_procesado' => 'datetime'];
    }
}
