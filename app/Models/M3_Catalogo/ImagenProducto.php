<?php

declare(strict_types=1);

namespace App\Models\M3_Catalogo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Imagen de un producto (JPG o PNG, hasta 5 MB, maximo 8 por producto; RN-M3-09). */
class ImagenProducto extends Model
{
    protected $table = 'imagenes_producto';

    protected $primaryKey = 'id_imagen';

    protected $fillable = [
        'id_producto',
        'id_variacion',
        'imagen_url',
        'formato',
        'tamano_bytes',
        'orden',
    ];

    protected $casts = [
        'id_producto' => 'integer',
        'id_variacion' => 'integer',
        'tamano_bytes' => 'integer',
        'orden' => 'integer',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function variacion(): BelongsTo
    {
        return $this->belongsTo(ProductoVariacion::class, 'id_variacion', 'id_variacion');
    }
}