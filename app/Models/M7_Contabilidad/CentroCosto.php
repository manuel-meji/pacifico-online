<?php

namespace App\Models\M7_Contabilidad;

use Illuminate\Database\Eloquent\Model;

/**
 * Centro de costo de un comercio (PC3: CentroCosto).
 * Tabla: centros_costo (M7_Contabilidad).
 */
class CentroCosto extends Model
{
    protected $table = 'centros_costo';

    protected $primaryKey = 'id_centro_costo';

    protected $fillable = [
        'id_comercio',
        'codigo',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'id_comercio' => 'integer',
        'activo' => 'boolean',
    ];

    public function scopeDeComercio($query, int $idComercio)
    {
        return $query->where('id_comercio', $idComercio);
    }
}