<?php

namespace App\Models\M1_Portal;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla cupon_usos: un uso de un cupón por pedido (contrato 1.5.0, SC-11).
 * id_pedido es una referencia lógica al pedido maestro de M5; M1 no lee la
 * tabla de pedidos.
 *
 * @property int $id_cupon_uso
 * @property int $id_cupon
 * @property int $id_cliente
 * @property int $id_pedido
 */
class CuponUso extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'cupon_usos';

    protected $primaryKey = 'id_cupon_uso';

    protected $fillable = [
        'id_cupon',
        'id_cliente',
        'id_pedido',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_cupon_uso' => 'integer',
            'id_cupon' => 'integer',
            'id_cliente' => 'integer',
            'id_pedido' => 'integer',
        ];
    }
}
