<?php

namespace App\Models\M7_Contabilidad;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Illuminate\Database\Eloquent\Model;

/**
 * Centro de costo de un comercio (PC3: CentroCosto).
 * Tabla: centros_costo (M7_Contabilidad).
 * Aislamiento por comercio: trait PerteneceAComercio del Núcleo (RN-12 / ADR-006).
 */
class CentroCosto extends Model
{
    use PerteneceAComercio;

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
}
