<?php

declare(strict_types=1);

namespace App\Models\M3_Catalogo;

use Illuminate\Database\Eloquent\Model;

/**
 * Estado del comercio visto desde M3 (tabla estado_comercios_catalogo).
 * Se mantiene con los eventos de M2 (ComercioAprobado, ComercioReactivado,
 * ComercioSuspendido). De aqui sale la visibilidad publica de los productos:
 * solo se muestran los de comercios con activo = true.
 */
class EstadoComercioCatalogo extends Model
{
    protected $table = 'estado_comercios_catalogo';

    protected $primaryKey = 'id_comercio';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id_comercio',
        'activo',
        'fecha_ultimo_evento',
        'id_ultimo_evento',
    ];

    protected $casts = [
        'id_comercio' => 'integer',
        'activo' => 'boolean',
        'fecha_ultimo_evento' => 'datetime',
    ];
}
