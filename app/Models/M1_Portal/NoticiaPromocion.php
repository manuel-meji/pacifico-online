<?php

namespace App\Models\M1_Portal;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla noticia_promociones: promoción que enlaza una noticia (contrato
 * 1.4.0, SC-08). Las dos tablas son de M1.
 */
class NoticiaPromocion extends Model
{
    protected $table = 'noticia_promociones';

    protected $primaryKey = 'id_noticia_promocion';

    protected $fillable = [
        'id_noticia_portal',
        'id_promocion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_noticia_promocion' => 'integer',
            'id_noticia_portal' => 'integer',
            'id_promocion' => 'integer',
        ];
    }
}
