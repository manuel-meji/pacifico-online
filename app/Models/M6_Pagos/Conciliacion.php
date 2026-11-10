<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Resultado de conciliar un período (tabla conciliaciones). Privado de M6. */
class Conciliacion extends Model
{
    protected $table = 'conciliaciones';

    protected $primaryKey = 'id_conciliacion';

    public $timestamps = false;

    protected $fillable = [
        'id_estado_conciliacion',
        'fecha_inicio',
        'fecha_fin',
        'total_cobrado_sistema',
        'total_reportado_pasarela',
        'fecha_generacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'total_cobrado_sistema' => 'decimal:2',
            'total_reportado_pasarela' => 'decimal:2',
            'fecha_generacion' => 'datetime',
        ];
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoConciliacion::class, 'id_estado_conciliacion', 'id_estado_conciliacion');
    }

    public function diferencias(): HasMany
    {
        return $this->hasMany(DiferenciaConciliacion::class, 'id_conciliacion', 'id_conciliacion');
    }
}
