<?php

namespace App\Models\M1_Portal;

use Carbon\CarbonInterface;
use Database\Factories\M1_Portal\ProductoDestacadoFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabla productos_destacados: selección de productos que M1 muestra en la
 * portada durante un periodo (RN-M1-11).
 * id_producto es una clave compartida: los datos se piden por contrato, sin JOIN.
 *
 * Desde 1.4.0 (SC-08) guarda el origen: 'solicitud' (pagado por el comercio,
 * con id_solicitud_destacado) o 'plataforma' (sin costo), y el id_comercio
 * dueño del producto, para el máximo de 3 activos por comercio.
 *
 * @property int $id_producto_destacado
 * @property int $id_producto
 * @property int|null $id_comercio
 * @property string $origen
 * @property int|null $id_solicitud_destacado
 * @property int $posicion
 * @property CarbonInterface $fecha_inicio
 * @property CarbonInterface $fecha_fin
 * @property bool $activo
 */
class ProductoDestacado extends Model
{
    /** @use HasFactory<ProductoDestacadoFactory> */
    use HasFactory;

    protected $table = 'productos_destacados';

    protected $primaryKey = 'id_producto_destacado';

    public const ORIGEN_SOLICITUD = 'solicitud';

    public const ORIGEN_PLATAFORMA = 'plataforma';

    protected $fillable = [
        'id_producto',
        'id_comercio',
        'origen',
        'id_solicitud_destacado',
        'posicion',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected $attributes = [
        'activo' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_producto_destacado' => 'integer',
            'id_producto' => 'integer',
            'id_comercio' => 'integer',
            'id_solicitud_destacado' => 'integer',
            'posicion' => 'integer',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    /**
     * Vigente si está activo y la fecha cae entre fecha_inicio y fecha_fin
     * (ambos extremos incluidos) (RN-M1-11).
     */
    public function estaVigente(DateTimeInterface $fecha): bool
    {
        return $this->activo
            && $this->fecha_inicio->lessThanOrEqualTo($fecha)
            && $this->fecha_fin->greaterThanOrEqualTo($fecha);
    }
}
