<?php

namespace App\Models\M5_Pedidos\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait para aislamiento multi-tenant por comercio (ADR-006 / Regla RN-12).
 * Aplica un Global Scope que restringe automáticamente las consultas al comercio activo.
 */
trait BelongsToComercio
{
    public static function bootBelongsToComercio(): void
    {
        static::addGlobalScope('comercio', function (Builder $builder) {
            $idComercio = static::obtenerComercioActivoId();
            if ($idComercio !== null) {
                $builder->where($builder->getModel()->getTable().'.id_comercio', $idComercio);
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->id_comercio)) {
                $idComercio = static::obtenerComercioActivoId();
                if ($idComercio !== null) {
                    $model->id_comercio = $idComercio;
                }
            }
        });
    }

    /**
     * Resuelve el ID del comercio activo en la sesión, usuario autenticado o encabezados.
     */
    public static function obtenerComercioActivoId(): ?int
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        try {
            if (session()->isStarted() && session()->has('id_comercio')) {
                return (int) session('id_comercio');
            }
        } catch (\Throwable) {
        }

        try {
            if (auth()->check() && isset(auth()->user()->id_comercio)) {
                return (int) auth()->user()->id_comercio;
            }
        } catch (\Throwable) {
        }

        try {
            if (request()?->hasHeader('X-Comercio-Id')) {
                return (int) request()->header('X-Comercio-Id');
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
