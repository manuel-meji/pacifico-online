<?php

namespace App\Listeners\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Models\M4_Inventario\EstadoComercioInventario;
use Illuminate\Support\Carbon;

/**
 * Eventos de M2 sobre el estado del comercio (figura 4.6c del PC3, RN-M4-009):
 *  - ComercioSuspendido: bloquea entradas, salidas manuales y ajustes del comercio.
 *  - ComercioAprobado y ComercioReactivado: los desbloquean.
 * Los pedidos pagados en curso no se alteran (SC-M4-11). Un evento repetido no se
 * aplica dos veces (id_evento) y uno más viejo que el último aplicado se ignora, así
 * un evento fuera de orden no deja al comercio en un estado incorrecto.
 */
class ProcesarEventosComercioListener
{
    use ProcesaEventosUnaVez;

    public function __construct(private readonly NucleoDependencia $nucleo) {}

    public function alAprobarComercio(object $evento): void
    {
        $this->aplicar($evento, 'ComercioAprobado', bloqueado: false);
    }

    public function alSuspenderComercio(object $evento): void
    {
        $this->aplicar($evento, 'ComercioSuspendido', bloqueado: true);
    }

    public function alReactivarComercio(object $evento): void
    {
        $this->aplicar($evento, 'ComercioReactivado', bloqueado: false);
    }

    private function aplicar(object $evento, string $nombre, bool $bloqueado): void
    {
        if (! $this->cargaValida($evento, $nombre, ['id_evento', 'id_comercio', 'fecha_hora'])) {
            return;
        }

        $idEvento = (string) $evento->id_evento;
        $fecha = Carbon::parse($evento->fecha_hora);

        $this->procesarUnaVez($this->nucleo, $idEvento, $nombre, function () use ($evento, $idEvento, $fecha, $bloqueado): void {
            $actual = EstadoComercioInventario::query()->whereKey((int) $evento->id_comercio)->lockForUpdate()->first();

            if ($actual !== null && $actual->fecha_evento->gt($fecha)) {
                return;
            }

            EstadoComercioInventario::query()->updateOrCreate(['id_comercio' => (int) $evento->id_comercio], [
                'bloqueado' => $bloqueado,
                'motivo' => $bloqueado ? (string) ($evento->motivo ?? 'Suspendido por M2') : null,
                'fecha_evento' => $fecha,
                'id_evento' => $idEvento,
            ]);
        });
    }
}
