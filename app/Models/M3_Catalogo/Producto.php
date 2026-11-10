<?php


namespace App\Models\M3_Catalogo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Producto extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'id_producto';
    
    protected $fillable = [
        'id_comercio',
        'id_categoria',
        'id_marca',
        'nombre',
        'descripcion',
        'video_url',
        'destacado',
        'estado_producto'
    ];

    protected $casts = [
        'destacado' => 'boolean',
    ];

    // Aislamiento por comercio (RN-12)
    public function scopeDelComercio(Builder $query, int $idComercio): Builder
    {
        return $query->where('id_comercio', $idComercio);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'id_marca', 'id_marca');
    }

    public function variaciones(): HasMany
    {
        return $this->hasMany(ProductoVariacion::class, 'id_producto', 'id_producto');
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(ImagenProducto::class, 'id_producto', 'id_producto');
    }

    public function relacionados(): HasMany
    {
        return $this->hasMany(ProductoRelacionado::class, 'id_producto', 'id_producto');
    }
}