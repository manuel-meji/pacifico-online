<?php

namespace App\Models\M4_Inventario;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de motivos de ajuste (tabla motivos_ajuste): merma, robo, danio,
 * sobrante, error_conteo y otro. No pertenece a un comercio.
 *
 * @property int $id_motivo_ajuste
 * @property string $codigo
 * @property string $nombre
 */
class MotivoAjuste extends Model
{
    protected $table = 'motivos_ajuste';

    protected $primaryKey = 'id_motivo_ajuste';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
