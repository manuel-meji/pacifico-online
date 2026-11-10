<?php

namespace App\Models\M3_Catalogo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marca extends Model
{
    protected $table = 'marcas';
    protected $primaryKey = 'id_marca';
    
    protected $fillable = [
        'nombre',
        'logotipo_url',
        'destacada'
    ];

    protected $casts = [
        'destacada' => 'boolean',
    ];

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_marca', 'id_marca');
    }
}