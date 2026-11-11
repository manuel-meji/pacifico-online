<?php

namespace App\Models\M1_Portal;

use Database\Factories\M1_Portal\CategoriaPortalFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabla categorias_portal: orden y visibilidad de las categorías de M3 en el
 * menú. La categoría y su nombre pertenecen a M3; M1 solo guarda su
 * presentación (id_categoria es una clave compartida, sin JOIN).
 *
 * @property int $id_categoria_portal
 * @property int $id_categoria
 * @property int $orden_visualizacion
 * @property bool $visible_en_menu
 * @property string|null $icono_url
 */
class CategoriaPortal extends Model
{
    /** @use HasFactory<CategoriaPortalFactory> */
    use HasFactory;

    protected $table = 'categorias_portal';

    protected $primaryKey = 'id_categoria_portal';

    protected $fillable = [
        'id_categoria',
        'orden_visualizacion',
        'visible_en_menu',
        'icono_url',
    ];

    protected $attributes = [
        'visible_en_menu' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_categoria_portal' => 'integer',
            'id_categoria' => 'integer',
            'orden_visualizacion' => 'integer',
            'visible_en_menu' => 'boolean',
        ];
    }

    /**
     * Categorías visibles en el menú, en el orden de visualización. La usa
     * BusquedaService para ordenar las categorías que entrega M3.
     *
     * @return Collection<int, CategoriaPortal>
     */
    public static function obtenerVisibles(): Collection
    {
        return static::query()
            ->where('visible_en_menu', true)
            ->orderBy('orden_visualizacion')
            ->get();
    }
}
