<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use App\Models\M6_Pagos\Concerns\CatalogoPorCodigo;
use Illuminate\Database\Eloquent\Model;

/** Catálogo estados_revision_diferencia (pendiente_revision, resuelta). Privado de M6. */
class EstadoRevisionDiferencia extends Model
{
    use CatalogoPorCodigo;

    public const PENDIENTE_REVISION = 'pendiente_revision';

    public const RESUELTA = 'resuelta';

    protected $table = 'estados_revision_diferencia';

    protected $primaryKey = 'id_estado_revision_diferencia';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'descripcion'];

    /** Traduce el catálogo al contrato (pendiente_de_revision | resuelto). */
    public static function valorContrato(string $codigo): string
    {
        return $codigo === self::RESUELTA ? 'resuelto' : 'pendiente_de_revision';
    }
}
