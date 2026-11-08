<?php

namespace App\Models\M4_Inventario;

use App\Enums\M4_Inventario\EstadoReservaCodigo;
use App\Enums\M4_Inventario\MotivoCierre;
use App\Models\Nucleo\Concerns\PerteneceAComercio;
use App\Services\M4_Inventario\Dominio\EstadosReserva\EstadoActiva;
use App\Services\M4_Inventario\Dominio\EstadosReserva\EstadoConfirmada;
use App\Services\M4_Inventario\Dominio\EstadosReserva\EstadoConsumida;
use App\Services\M4_Inventario\Dominio\EstadosReserva\EstadoLiberada;
use App\Services\M4_Inventario\Dominio\EstadosReserva\EstadoReserva;
use App\Services\M4_Inventario\Dominio\EstadosReserva\EstadoVencida;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Reserva temporal de unidades de una existencia (tabla reservas). La clave es un
 * UUID que genera M4. El trigger tr_reservas_transicion solo permite 1→2, 1→4,
 * 1→5, 2→3 y 2→4; cada cambio queda en historial (jsonb).
 *
 * @property string $id_reserva
 * @property int $id_comercio
 * @property int $id_existencia
 * @property EstadoReservaCodigo $id_estado_reserva
 * @property int|null $id_pedido
 * @property int|null $id_subpedido
 * @property int $cantidad
 * @property Carbon $fecha_creacion
 * @property Carbon $fecha_vencimiento
 * @property string $clave_idempotencia
 * @property list<array{estado: string, fecha: string, motivo: string}> $historial
 * @property-read Existencia $existencia
 */
class Reserva extends Model
{
    use PerteneceAComercio;

    protected $table = 'reservas';

    protected $primaryKey = 'id_reserva';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id_comercio' => 'integer',
            'id_existencia' => 'integer',
            'id_estado_reserva' => EstadoReservaCodigo::class,
            'id_pedido' => 'integer',
            'id_subpedido' => 'integer',
            'cantidad' => 'integer',
            'fecha_creacion' => 'datetime',
            'fecha_vencimiento' => 'datetime',
            'historial' => 'array',
        ];
    }

    /** @return BelongsTo<Existencia, $this> */
    public function existencia(): BelongsTo
    {
        return $this->belongsTo(Existencia::class, 'id_existencia', 'id_existencia');
    }

    public function estadoCodigo(): EstadoReservaCodigo
    {
        return $this->id_estado_reserva;
    }

    /**
     * Reserva ACTIVA nueva sobre la existencia, con su vencimiento y su clave de idempotencia.
     */
    public static function crearActiva(Existencia $existencia, int $cantidad, int $minutos, string $clave): self
    {
        $ahora = Carbon::now();

        $reserva = new self([
            'id_reserva' => (string) Str::uuid(),
            'id_comercio' => $existencia->id_comercio,
            'id_existencia' => $existencia->id_existencia,
            'id_estado_reserva' => EstadoReservaCodigo::Activa,
            'cantidad' => $cantidad,
            'fecha_creacion' => $ahora,
            'fecha_vencimiento' => $ahora->copy()->addMinutes($minutos),
            'clave_idempotencia' => $clave,
            'historial' => [['estado' => 'activa', 'fecha' => $ahora->toIso8601String(), 'motivo' => 'creada']],
        ]);
        $reserva->setRelation('existencia', $existencia);

        return $reserva;
    }

    /** Estado actual como objeto del patrón State. */
    public function estado(): EstadoReserva
    {
        return match ($this->estadoCodigo()) {
            EstadoReservaCodigo::Activa => new EstadoActiva,
            EstadoReservaCodigo::Confirmada => new EstadoConfirmada,
            EstadoReservaCodigo::Consumida => new EstadoConsumida,
            EstadoReservaCodigo::Liberada => new EstadoLiberada,
            EstadoReservaCodigo::Vencida => new EstadoVencida,
        };
    }

    public function confirmar(int $idPedido, int $idSubpedido): void
    {
        $this->estado()->confirmar($this, $idPedido, $idSubpedido);
    }

    public function consumir(): void
    {
        $this->estado()->consumir($this);
    }

    public function liberar(MotivoCierre $motivo): void
    {
        $this->estado()->liberar($this, $motivo);
    }

    public function vencer(): void
    {
        $this->estado()->vencer($this);
    }

    /** Solo una reserva ACTIVA vence; CONFIRMADA conserva sus unidades sin plazo (RN-M4-010). */
    public function estaVencida(DateTimeInterface $ahora): bool
    {
        return $this->estadoCodigo() === EstadoReservaCodigo::Activa && $this->fecha_vencimiento <= $ahora;
    }

    public function vinculoCoincide(int $idPedido, int $idSubpedido): bool
    {
        return $this->id_pedido === $idPedido && $this->id_subpedido === $idSubpedido;
    }

    /** Lo usan los estados: cambia el código y agrega la transición al historial. */
    public function cambiarA(EstadoReservaCodigo $estado, string $motivo): void
    {
        $historial = $this->historial ?? [];
        $historial[] = ['estado' => $estado->codigo(), 'fecha' => Carbon::now()->toIso8601String(), 'motivo' => $motivo];
        $this->id_estado_reserva = $estado;
        $this->historial = $historial;
    }

    /**
     * Esquema Reserva del contrato.
     *
     * @return array<string, mixed>
     */
    public function aContrato(): array
    {
        $estado = $this->estadoCodigo();
        $historial = $this->historial ?? [];
        $ultimo = $historial === [] ? null : $historial[array_key_last($historial)];
        $cierre = $estado->esCerrada() ? $ultimo : null;

        return [
            'id_reserva' => $this->id_reserva,
            'id_comercio' => $this->id_comercio,
            'id_existencia' => $this->id_existencia,
            'id_variacion' => (int) $this->existencia->id_variacion,
            'id_ubicacion' => (int) $this->existencia->id_ubicacion,
            'cantidad' => $this->cantidad,
            'estado_reserva' => $estado->codigo(),
            'id_pedido' => $this->id_pedido,
            'id_subpedido' => $this->id_subpedido,
            'fecha_creacion' => $this->fecha_creacion->toIso8601String(),
            'fecha_expiracion' => $this->fecha_vencimiento->toIso8601String(),
            'fecha_resolucion' => count($historial) > 1 ? $ultimo['fecha'] : null,
            'motivo_cierre' => $cierre['motivo'] ?? null,
        ];
    }
}
