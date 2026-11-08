<?php

namespace App\Models\M4_Inventario;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Último estado de un comercio que M4 recibió por evento de M2 (tabla
 * estados_comercio_inventario). bloqueado = true después de ComercioSuspendido:
 * no se permiten movimientos manuales ni ajustes (RN-M4-009).
 *
 * @property int $id_comercio
 * @property bool $bloqueado
 * @property string|null $motivo
 * @property Carbon $fecha_evento
 * @property string $id_evento
 */
class EstadoComercioInventario extends Model
{
    use PerteneceAComercio;

    protected $table = 'estados_comercio_inventario';

    protected $primaryKey = 'id_comercio';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id_comercio' => 'integer',
            'bloqueado' => 'boolean',
            'fecha_evento' => 'datetime',
        ];
    }
}
