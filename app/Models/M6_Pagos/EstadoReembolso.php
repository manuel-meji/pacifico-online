<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use App\Models\M6_Pagos\Concerns\CatalogoPorCodigo;
use Illuminate\Database\Eloquent\Model;

/** Catálogo estados_reembolso (solicitado, procesado, rechazado). Privado de M6. */
class EstadoReembolso extends Model
{
    use CatalogoPorCodigo;

    public const SOLICITADO = 'solicitado';

    public const PROCESADO = 'procesado';

    public const RECHAZADO = 'rechazado';

    protected $table = 'estados_reembolso';

    protected $primaryKey = 'id_estado_reembolso';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'descripcion'];

    public static function valorContrato(string $codigo): string
    {
        return match ($codigo) {
            self::PROCESADO => 'procesado',
            self::RECHAZADO => 'rechazado',
            default => 'aceptado',
        };
    }
}
