<?php

namespace App\Models\M1_Portal;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla promocion_variaciones: variaciones específicas de una promoción con
 * alcance 'variaciones' (contrato 1.4.0, SC-08). id_variacion es una clave
 * compartida con M3: sus datos se piden por contrato, sin JOIN.
 *
 * @property int $id_promocion_variacion
 * @property int $id_promocion
 * @property int $id_variacion
 */
class PromocionVariacion extends Model
{
    protected $table = 'promocion_variaciones';

    protected $primaryKey = 'id_promocion_variacion';

    protected $fillable = [
        'id_promocion',
        'id_variacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_promocion_variacion' => 'integer',
            'id_promocion' => 'integer',
            'id_variacion' => 'integer',
        ];
    }
}
