<?php

declare(strict_types=1);

namespace App\Models\M3_Catalogo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Variacion de un producto: lo que se vende (SKU, precio, IVA, descuento y disponibilidad).
 * id_comercio es una referencia logica a M2 (sin relacion Eloquent hacia otro modulo).
 */
class ProductoVariacion extends Model
{
    protected $table = 'producto_variaciones';

    protected $primaryKey = 'id_variacion';

    protected $fillable = [
        'id_producto',
        'id_comercio',
        'sku',
        'precio',
        'tarifa_impuesto',
        'codigo_barras',
        'codigo_fabricante',
        'disponible',
        'tipo_descuento',
        'valor_descuento',
    ];

    protected $casts = [
        'id_producto' => 'integer',
        'id_comercio' => 'integer',
        'precio' => 'decimal:2',
        'tarifa_impuesto' => 'decimal:2',
        'valor_descuento' => 'decimal:2',
        'disponible' => 'boolean',
    ];

    /** Filtra por comercio (RN-12) sin relaciones hacia otros modulos. */
    public function scopeDelComercio(Builder $consulta, int $idComercio): Builder
    {
        return $consulta->where($this->getTable().'.id_comercio', $idComercio);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function atributos(): HasMany
    {
        return $this->hasMany(AtributoVariacion::class, 'id_variacion', 'id_variacion');
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(ImagenProducto::class, 'id_variacion', 'id_variacion');
    }
}