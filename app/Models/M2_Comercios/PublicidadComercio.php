<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Publicidad contratada por el comercio; define si es destacado (RF-011, RF-021). */
class PublicidadComercio extends Model
{
    public const PENDIENTE = 'Pendiente';

    public const APROBADA = 'Aprobada';

    public const RECHAZADA = 'Rechazada';

    /** Periodos que puede solicitar el encargado del comercio. */
    public const DIAS_PERMITIDOS = [7, 15, 30];

    protected $table = 'publicidad_comercio';

    protected $primaryKey = 'id_publicidad_comercio';

    protected $guarded = ['id_publicidad_comercio'];

    protected $casts = ['inicio' => 'date', 'fin' => 'date', 'activa' => 'boolean', 'dias_solicitados' => 'integer'];

    public function estaPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    public function scopeVigentes(Builder $consulta, ?DateTimeInterface $fecha = null): Builder
    {
        $dia = ($fecha ?? now())->format('Y-m-d');

        return $consulta->where('activa', true)
            ->where('estado', self::APROBADA)
            ->whereDate('inicio', '<=', $dia)
            ->whereDate('fin', '>=', $dia);
    }

    public function estaVigente(?DateTimeInterface $fecha = null): bool
    {
        $dia = ($fecha ?? now())->format('Y-m-d');

        return $this->activa
            && ($this->estado ?? self::APROBADA) === self::APROBADA
            && $this->inicio->format('Y-m-d') <= $dia
            && $this->fin->format('Y-m-d') >= $dia;
    }

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class, 'id_comercio', 'id_comercio');
    }
}
