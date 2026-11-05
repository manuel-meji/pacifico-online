<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use App\Models\M6_Pagos\Concerns\CatalogoPorCodigo;
use Illuminate\Database\Eloquent\Model;

/** Catálogo estados_notificacion_pago (recibida, procesada, duplicada, rechazada). Privado de M6. */
class EstadoNotificacionPago extends Model
{
    use CatalogoPorCodigo;

    public const RECIBIDA = 'recibida';

    public const PROCESADA = 'procesada';

    public const DUPLICADA = 'duplicada';

    public const RECHAZADA = 'rechazada';

    protected $table = 'estados_notificacion_pago';

    protected $primaryKey = 'id_estado_notificacion_pago';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'descripcion'];
}
