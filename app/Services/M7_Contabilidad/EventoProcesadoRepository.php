<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Models\M7_Contabilidad\EventoProcesado;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class EventoProcesadoRepository
{
    public function yaProcesado(string $eventoId): bool
    {
        return DB::table('eventos_procesados')->where('evento_id', $eventoId)->where('estado', 'PROCESADO')->exists();
    }

    public function procesar(string $eventoId, string $tipo, Closure $operacion): void
    {
        if (! Str::isUuid($eventoId)) {
            throw new InvalidArgumentException('El identificador del evento debe ser un UUID válido.');
        }

        DB::transaction(function () use ($eventoId, $tipo, $operacion): void {
            DB::table('eventos_procesados')->insertOrIgnore([
                'evento_id' => $eventoId, 'tipo_evento' => $tipo, 'estado' => 'PENDIENTE',
                'fecha_recepcion' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $evento = DB::table('eventos_procesados')->where('evento_id', $eventoId)->lockForUpdate()->first();
            if ($evento === null || $evento->tipo_evento !== $tipo) {
                throw new LogicException('El identificador del evento pertenece a otro tipo o no se pudo registrar.');
            }
            $registro = new EventoProcesado($eventoId, $tipo, $evento->estado);
            if ($registro->estaProcesado()) {
                $operacion(true);

                return;
            }

            $operacion(false);
            $registro->marcarProcesado();
            DB::table('eventos_procesados')->where('evento_id', $eventoId)->update([
                'estado' => $registro->estado(), 'fecha_procesamiento' => now(),
                'ultimo_error' => null, 'updated_at' => now(),
            ]);
        });
    }
}
