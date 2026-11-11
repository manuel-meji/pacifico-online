<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use App\Models\M6_Pagos\Concerns\CatalogoPorCodigo;
use Illuminate\Database\Eloquent\Model;

/** Catálogo estados_conciliacion (en_proceso, parcial, completada). Privado de M6. */
class EstadoConciliacion extends Model
{
    use CatalogoPorCodigo;

    public const EN_PROCESO = 'en_proceso';

    public const PARCIAL = 'parcial';

    public const COMPLETADA = 'completada';

    protected $table = 'estados_conciliacion';

    protected $primaryKey = 'id_estado_conciliacion';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'descripcion'];

    /** Traduce el catálogo al contrato (completa | parcial). Decisión abierta 1. */
    public static function valorContrato(string $codigo): string
    {
        return $codigo === self::COMPLETADA ? 'completa' : 'parcial';
    }
}
