<?php


namespace App\Models\M3_Catalogo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoRelacionado extends Model
{
    protected $table = 'productos_relacionados';
    protected $primaryKey = ['id_producto', 'id_producto_relacionado'];
    public $incrementing = false;
    
    protected $fillable = [
        'id_producto',
        'id_producto_relacionado'
    ];

    public function productoPrincipal(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function productoRelacionado(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto_relacionado', 'id_producto');
    }
}