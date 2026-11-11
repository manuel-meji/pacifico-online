<?php

declare(strict_types=1);

namespace App\Models\M3_Catalogo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Atributo de una variacion como par tipo/valor (ej. Color: Negro). Unico por variacion y tipo. */
class AtributoVariacion extends Model
{
    protected $table = 'atributos_variacion';

    protected $primaryKey = 'id_atributo';

    protected $fillable = [
        'id_variacion',
        'tipo_atributo',
        'valor',
    ];

    protected $casts = [
        'id_variacion' => 'integer',
    ];

    public function variacion(): BelongsTo
    {
        return $this->belongsTo(ProductoVariacion::class, 'id_variacion', 'id_variacion');
    }
}