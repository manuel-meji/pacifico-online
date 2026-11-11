<?php

declare(strict_types=1);

namespace App\Models\M3_Catalogo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Categoria del catalogo, con jerarquia por id_categoria_padre (RN-M3-13). */
class Categoria extends Model
{
    protected $table = 'categorias';

    protected $primaryKey = 'id_categoria';

    protected $fillable = [
        'id_categoria_padre',
        'nombre',
        'descripcion',
    ];

    protected $casts = [
        'id_categoria_padre' => 'integer',
    ];

    public function subcategorias(): HasMany
    {
        return $this->hasMany(Categoria::class, 'id_categoria_padre', 'id_categoria');
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria_padre', 'id_categoria');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_categoria', 'id_categoria');
    }
}