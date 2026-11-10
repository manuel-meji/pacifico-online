<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use App\Models\M6_Pagos\Concerns\CatalogoPorCodigo;
use Illuminate\Database\Eloquent\Model;

/** Catálogo tipos_diferencia (monto_distinto, faltante_en_pasarela, faltante_en_sistema). Privado de M6. */
class TipoDiferencia extends Model
{
    use CatalogoPorCodigo;

    public const MONTO_DISTINTO = 'monto_distinto';

    public const FALTANTE_EN_PASARELA = 'faltante_en_pasarela';

    public const FALTANTE_EN_SISTEMA = 'faltante_en_sistema';

    protected $table = 'tipos_diferencia';

    protected $primaryKey = 'id_tipo_diferencia';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'descripcion'];
}
