<?php

namespace App\Models\M1_Portal;

use App\Services\M1_Portal\DTO\PromocionDTO;
use Carbon\CarbonInterface;
use Database\Factories\M1_Portal\PromocionPortalFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabla promociones_portal: piezas del carrusel que dirigen a un producto,
 * comercio, categoría o enlace. No calcula descuentos (los precios son de M3 y
 * los cupones de M5). id_producto, id_comercio e id_categoria son claves
 * compartidas; solo una se llena, según tipo_destino.
 *
 * @property int $id_promocion_portal
 * @property string $titulo_promocion
 * @property string|null $descripcion_promocion
 * @property string $imagen_url
 * @property string $tipo_destino
 * @property int|null $id_producto
 * @property int|null $id_comercio
 * @property int|null $id_categoria
 * @property string|null $enlace_url
 * @property int $orden_visualizacion
 * @property CarbonInterface $fecha_inicio
 * @property CarbonInterface $fecha_fin
 * @property bool $activa
 */
class PromocionPortal extends Model
{
    /** @use HasFactory<PromocionPortalFactory> */
    use HasFactory;

    /**
     * Valores que admite chk_promociones_portal_tipo_destino.
     *
     * @var list<string>
     */
    public const TIPOS_DESTINO = ['producto', 'comercio', 'categoria', 'enlace'];

    protected $table = 'promociones_portal';

    protected $primaryKey = 'id_promocion_portal';

    protected $fillable = [
        'titulo_promocion',
        'descripcion_promocion',
        'imagen_url',
        'tipo_destino',
        'id_producto',
        'id_comercio',
        'id_categoria',
        'enlace_url',
        'orden_visualizacion',
        'fecha_inicio',
        'fecha_fin',
        'activa',
    ];

    protected $attributes = [
        'activa' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_promocion_portal' => 'integer',
            'id_producto' => 'integer',
            'id_comercio' => 'integer',
            'id_categoria' => 'integer',
            'orden_visualizacion' => 'integer',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
            'activa' => 'boolean',
        ];
    }

    /**
     * Vigente si está activa y la fecha cae entre fecha_inicio y fecha_fin,
     * ambos incluidos (RN-M1-10).
     */
    public function estaVigente(DateTimeInterface $fecha): bool
    {
        return $this->activa
            && $this->fecha_inicio->lessThanOrEqualTo($fecha)
            && $this->fecha_fin->greaterThanOrEqualTo($fecha);
    }

    public function aDTO(): PromocionDTO
    {
        return new PromocionDTO(
            idPromocion: $this->id_promocion_portal,
            titulo: $this->titulo_promocion,
            descripcion: $this->descripcion_promocion,
            imagenUrl: $this->imagen_url,
            tipoDestino: $this->tipo_destino,
            idDestino: match ($this->tipo_destino) {
                'producto' => $this->id_producto,
                'comercio' => $this->id_comercio,
                'categoria' => $this->id_categoria,
                default => null,
            },
            enlaceUrl: $this->enlace_url,
            orden: $this->orden_visualizacion,
            fechaInicio: $this->fecha_inicio,
            fechaFin: $this->fecha_fin,
        );
    }
}
