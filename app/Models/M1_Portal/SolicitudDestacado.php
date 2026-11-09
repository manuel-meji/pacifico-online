<?php

namespace App\Models\M1_Portal;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabla solicitudes_destacado: un comercio pide destacar uno de sus productos
 * por 7, 15 o 30 días y la administración la aprueba o la rechaza (contrato
 * 1.4.0, SC-08). monto es la tarifa vigente al solicitar, la que se cobra al
 * aprobar.
 *
 * Aislamiento por comercio (RN-12): PerteneceAComercio filtra por el comercio
 * de la sesión; la administración opera con el acceso auditado del núcleo.
 *
 * @property int $id_solicitud_destacado
 * @property int $id_comercio
 * @property int $id_producto
 * @property int $periodo_dias
 * @property string $monto
 * @property string $moneda
 * @property string $estado
 * @property string|null $motivo_rechazo
 * @property int $id_usuario_solicita
 * @property int|null $id_usuario_resuelve
 * @property CarbonInterface|null $fecha_resolucion
 * @property CarbonInterface $created_at
 */
class SolicitudDestacado extends Model
{
    use PerteneceAComercio;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_APROBADA = 'aprobada';

    public const ESTADO_RECHAZADA = 'rechazada';

    /**
     * @var list<string>
     */
    public const ESTADOS = [self::ESTADO_PENDIENTE, self::ESTADO_APROBADA, self::ESTADO_RECHAZADA];

    protected $table = 'solicitudes_destacado';

    protected $primaryKey = 'id_solicitud_destacado';

    protected $fillable = [
        'id_comercio',
        'id_producto',
        'periodo_dias',
        'monto',
        'moneda',
        'estado',
        'motivo_rechazo',
        'id_usuario_solicita',
        'id_usuario_resuelve',
        'fecha_resolucion',
    ];

    protected $attributes = [
        'estado' => self::ESTADO_PENDIENTE,
        'moneda' => 'CRC',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_solicitud_destacado' => 'integer',
            'id_comercio' => 'integer',
            'id_producto' => 'integer',
            'periodo_dias' => 'integer',
            'monto' => 'decimal:2',
            'id_usuario_solicita' => 'integer',
            'id_usuario_resuelve' => 'integer',
            'fecha_resolucion' => 'datetime',
        ];
    }

    /**
     * Esquema SolicitudDestacado del contrato. $destacado es el destacado que
     * creó la aprobación, si la hay.
     *
     * @return array<string, mixed>
     */
    public function aRespuesta(?ProductoDestacado $destacado = null): array
    {
        return [
            'id_solicitud_destacado' => $this->id_solicitud_destacado,
            'id_comercio' => $this->id_comercio,
            'id_producto' => $this->id_producto,
            'periodo_dias' => $this->periodo_dias,
            'monto' => (float) $this->monto,
            'moneda' => $this->moneda,
            'estado' => $this->estado,
            'motivo_rechazo' => $this->motivo_rechazo,
            'fecha_solicitud' => $this->created_at?->toIso8601String(),
            'fecha_resolucion' => $this->fecha_resolucion?->toIso8601String(),
            'fecha_inicio' => $destacado?->fecha_inicio->toIso8601String(),
            'fecha_fin' => $destacado?->fecha_fin->toIso8601String(),
        ];
    }
}
