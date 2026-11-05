<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use App\Models\M6_Pagos\Concerns\CatalogoPorCodigo;
use Illuminate\Database\Eloquent\Model;

/** Catálogo estados_pago (pendiente, confirmado, rechazado). Privado de M6. */
class EstadoPago extends Model
{
    use CatalogoPorCodigo;

    public const PENDIENTE = 'pendiente';

    public const CONFIRMADO = 'confirmado';

    public const RECHAZADO = 'rechazado';

    protected $table = 'estados_pago';

    protected $primaryKey = 'id_estado_pago';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'descripcion'];
}
