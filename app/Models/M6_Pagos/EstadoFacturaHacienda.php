<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use App\Models\M6_Pagos\Concerns\CatalogoPorCodigo;
use Illuminate\Database\Eloquent\Model;

/** Catálogo estados_factura_hacienda (pendiente_envio, enviada, aceptada, rechazada). Privado de M6. */
class EstadoFacturaHacienda extends Model
{
    use CatalogoPorCodigo;

    public const PENDIENTE_ENVIO = 'pendiente_envio';

    public const ENVIADA = 'enviada';

    public const ACEPTADA = 'aceptada';

    public const RECHAZADA = 'rechazada';

    protected $table = 'estados_factura_hacienda';

    protected $primaryKey = 'id_estado_factura_hacienda';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'descripcion'];

    /**
     * Traduce el código del catálogo al valor del contrato OpenAPI
     * (pendiente | aceptada | rechazada). Decisión abierta 1 del documento de pendientes.
     */
    public static function valorContrato(string $codigo): string
    {
        return match ($codigo) {
            self::ACEPTADA => 'aceptada',
            self::RECHAZADA => 'rechazada',
            default => 'pendiente',
        };
    }
}
