<?php

namespace App\Models\M1_Portal;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla cupones: código de descuento que la persona compradora escribe en el
 * checkout de M5 (contrato 1.5.0, SC-11).
 *
 * - id_comercio null: cupón global de la plataforma, descuenta sobre todo el
 *   subtotal. Con valor: cupón de ese comercio, descuenta solo sobre el
 *   subtotal de sus productos.
 * - Se puede usar si está activo, la fecha cae entre fecha_inicio y fecha_fin
 *   (ambas incluidas) y no llegó a limite_usos_total.
 *
 * Aislamiento por comercio (RN-12) con PerteneceAComercio: cada comercio ve
 * solo los suyos y la administración (acceso auditado) los ve todos. La
 * validación y el registro de usos para M5 se hacen en modo sistema.
 *
 * @property int $id_cupon
 * @property int|null $id_comercio
 * @property string $codigo
 * @property string|null $descripcion
 * @property string $tipo_descuento
 * @property string $valor
 * @property string|null $monto_minimo_compra
 * @property CarbonInterface $fecha_inicio
 * @property CarbonInterface $fecha_fin
 * @property int|null $limite_usos_total
 * @property int $usos_actuales
 * @property int $limite_usos_por_cliente
 * @property bool $activo
 * @property int $id_usuario_crea
 */
class Cupon extends Model
{
    use PerteneceAComercio;

    public const TIPO_PORCENTAJE = 'porcentaje';

    public const TIPO_MONTO_FIJO = 'monto_fijo';

    public const ESTADO_PROGRAMADO = 'programado';

    public const ESTADO_VIGENTE = 'vigente';

    public const ESTADO_VENCIDO = 'vencido';

    public const ESTADO_AGOTADO = 'agotado';

    public const ESTADO_DESACTIVADO = 'desactivado';

    /**
     * @var list<string>
     */
    public const ESTADOS = [
        self::ESTADO_PROGRAMADO,
        self::ESTADO_VIGENTE,
        self::ESTADO_VENCIDO,
        self::ESTADO_AGOTADO,
        self::ESTADO_DESACTIVADO,
    ];

    protected $table = 'cupones';

    protected $primaryKey = 'id_cupon';

    protected $fillable = [
        'id_comercio',
        'codigo',
        'descripcion',
        'tipo_descuento',
        'valor',
        'monto_minimo_compra',
        'fecha_inicio',
        'fecha_fin',
        'limite_usos_total',
        'usos_actuales',
        'limite_usos_por_cliente',
        'activo',
        'id_usuario_crea',
    ];

    protected $attributes = [
        'usos_actuales' => 0,
        'limite_usos_por_cliente' => 1,
        'activo' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_cupon' => 'integer',
            'id_comercio' => 'integer',
            'valor' => 'decimal:2',
            'monto_minimo_compra' => 'decimal:2',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
            'limite_usos_total' => 'integer',
            'usos_actuales' => 'integer',
            'limite_usos_por_cliente' => 'integer',
            'activo' => 'boolean',
            'id_usuario_crea' => 'integer',
        ];
    }

    /**
     * @return HasMany<CuponUso, $this>
     */
    public function usos(): HasMany
    {
        return $this->hasMany(CuponUso::class, 'id_cupon', 'id_cupon');
    }

    public function esGlobal(): bool
    {
        return $this->id_comercio === null;
    }

    public function agotado(): bool
    {
        return $this->limite_usos_total !== null && $this->usos_actuales >= $this->limite_usos_total;
    }

    /**
     * Estado para mostrar: desactivado manda sobre las fechas y los usos.
     */
    public function estado(DateTimeInterface $ahora): string
    {
        return match (true) {
            ! $this->activo => self::ESTADO_DESACTIVADO,
            $this->fecha_fin->lessThan($ahora) => self::ESTADO_VENCIDO,
            $this->agotado() => self::ESTADO_AGOTADO,
            $this->fecha_inicio->greaterThan($ahora) => self::ESTADO_PROGRAMADO,
            default => self::ESTADO_VIGENTE,
        };
    }

    /**
     * Monto en colones que el cupón descuenta de $base, a 2 decimales y nunca
     * mayor que la base (un monto fijo no deja el subtotal en negativo).
     */
    public function descuentoSobre(float $base): float
    {
        $descuento = $this->tipo_descuento === self::TIPO_PORCENTAJE
            ? $base * (float) $this->valor / 100
            : (float) $this->valor;

        return round(max(0.0, min($descuento, $base)), 2);
    }

    /**
     * Esquema Cupon del contrato.
     *
     * @return array<string, mixed>
     */
    public function aRespuesta(DateTimeInterface $ahora): array
    {
        return [
            'id_cupon' => $this->id_cupon,
            'id_comercio' => $this->id_comercio,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'tipo_descuento' => $this->tipo_descuento,
            'valor' => (float) $this->valor,
            'monto_minimo_compra' => $this->monto_minimo_compra === null ? null : (float) $this->monto_minimo_compra,
            'fecha_inicio' => $this->fecha_inicio->toIso8601String(),
            'fecha_fin' => $this->fecha_fin->toIso8601String(),
            'limite_usos_total' => $this->limite_usos_total,
            'usos_actuales' => $this->usos_actuales,
            'limite_usos_por_cliente' => $this->limite_usos_por_cliente,
            'activo' => $this->activo,
            'estado' => $this->estado($ahora),
        ];
    }
}
