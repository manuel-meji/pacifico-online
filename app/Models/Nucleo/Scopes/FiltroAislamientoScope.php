<?php

declare(strict_types=1);

namespace App\Models\Nucleo\Scopes;

use App\Services\Nucleo\ContextoAislamiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

abstract class FiltroAislamientoScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $contexto = $this->contexto();

        if ($contexto->esModoSistema()) {
            return;
        }

        $idActual = $contexto->idActual();

        // Falla cerrado: sin ámbito establecido no se devuelve ningún registro.
        if ($idActual === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn($this->columna()), $idActual);
    }

    abstract protected function columna(): string;

    abstract protected function contexto(): ContextoAislamiento;
}