<?php

namespace App\Models\M1_Portal;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabla tarifas_destacado: tarifa fija de cada periodo de producto destacado
 * (7, 15 o 30 días), que define la administración (contrato 1.4.0, SC-08).
 * id_usuario_actualiza es una clave compartida con el núcleo.
 *
 * @property int $id_tarifa_destacado
 * @property int $periodo_dias
 * @property string $monto
 * @property string $moneda
 * @property int|null $id_usuario_actualiza
 * @property CarbonInterface|null $updated_at
 */
class TarifaDestacado extends Model
{
    /**
     * Periodos que se pueden solicitar, en días.
     *
     * @var list<int>
     */
    public const PERIODOS = [7, 15, 30];

    protected $table = 'tarifas_destacado';

    protected $primaryKey = 'id_tarifa_destacado';

    protected $fillable = [
        'periodo_dias',
        'monto',
        'moneda',
        'id_usuario_actualiza',
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
            'id_tarifa_destacado' => 'integer',
            'periodo_dias' => 'integer',
            'monto' => 'decimal:2',
            'id_usuario_actualiza' => 'integer',
        ];
    }

    /**
     * Esquema TarifaDestacado del contrato.
     *
     * @return array{periodo_dias: int, monto: float, moneda: string, actualizada: string|null}
     */
    public function aRespuesta(): array
    {
        return [
            'periodo_dias' => $this->periodo_dias,
            'monto' => (float) $this->monto,
            'moneda' => $this->moneda,
            'actualizada' => $this->updated_at?->toIso8601String(),
        ];
    }
}
