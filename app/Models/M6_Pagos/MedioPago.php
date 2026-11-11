<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use App\Models\M6_Pagos\Concerns\CatalogoPorCodigo;
use Illuminate\Database\Eloquent\Model;

/** Catálogo medios_pago (tarjeta, sinpe, paypal). Privado de M6. */
class MedioPago extends Model
{
    use CatalogoPorCodigo;

    protected $table = 'medios_pago';

    protected $primaryKey = 'id_medio_pago';

    public $timestamps = false;

    protected $fillable = ['nombre_medio'];

    protected static function columnaCodigo(): string
    {
        return 'nombre_medio';
    }
}
