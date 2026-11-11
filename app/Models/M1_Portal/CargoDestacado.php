<?php

namespace App\Models\M1_Portal;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabla cargos_destacado: cargo de un destacado pagado que se aprobó
 * (contrato 1.4.0, SC-08). Se descuenta de la liquidación del comercio; quien
 * liquida lo consulta con ComercialPortalContract::listarCargosDestacado. No
 * se modifica después de creado (el periodo no se reembolsa).
 *
 * @property int $id_cargo_destacado
 * @property int $id_comercio
 * @property int $id_solicitud_destacado
 * @property int $id_producto_destacado
 * @property int $id_producto
 * @property int $periodo_dias
 * @property string $monto
 * @property string $moneda
 * @property CarbonInterface $fecha_cargo
 * @property CarbonInterface $fecha_inicio
 * @property CarbonInterface $fecha_fin
 */
class CargoDestacado extends Model
{
    use PerteneceAComercio;

    protected $table = 'cargos_destacado';

    protected $primaryKey = 'id_cargo_destacado';

    protected $fillable = [
        'id_comercio',
        'id_solicitud_destacado',
        'id_producto_destacado',
        'id_producto',
        'periodo_dias',
        'monto',
        'moneda',
        'fecha_cargo',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $attributes = [
        'moneda' => 'CRC',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_cargo_destacado' => 'integer',
            'id_comercio' => 'integer',
            'id_solicitud_destacado' => 'integer',
            'id_producto_destacado' => 'integer',
            'id_producto' => 'integer',
            'periodo_dias' => 'integer',
            'monto' => 'decimal:2',
            'fecha_cargo' => 'datetime',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
        ];
    }

    /**
     * Esquema CargoDestacado del contrato.
     *
     * @return array<string, mixed>
     */
    public function aRespuesta(): array
    {
        return [
            'id_cargo_destacado' => $this->id_cargo_destacado,
            'id_comercio' => $this->id_comercio,
            'id_solicitud_destacado' => $this->id_solicitud_destacado,
            'id_producto' => $this->id_producto,
            'periodo_dias' => $this->periodo_dias,
            'monto' => (float) $this->monto,
            'moneda' => $this->moneda,
            'fecha_cargo' => $this->fecha_cargo->toIso8601String(),
            'fecha_inicio' => $this->fecha_inicio->toIso8601String(),
            'fecha_fin' => $this->fecha_fin->toIso8601String(),
        ];
    }
}
