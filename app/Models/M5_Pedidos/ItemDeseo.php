<?php

declare(strict_types=1);

namespace App\Models\M5_Pedidos;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Item de la lista de deseos de un cliente (M5 Carrito y pedidos).
 *
 * @property int $id_deseo
 * @property int $id_cliente
 * @property int $id_variacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ItemDeseo extends Model
{
    protected $table = 'pedidos_deseos';

    protected $primaryKey = 'id_deseo';

    protected $fillable = [
        'id_cliente',
        'id_variacion',
    ];

    protected function casts(): array
    {
        return [
            'id_deseo' => 'integer',
            'id_cliente' => 'integer',
            'id_variacion' => 'integer',
        ];
    }
}
