<?php

namespace App\Models\M1_Portal;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla promociones: descuento que un comercio aplica a uno de sus productos,
 * completo o a variaciones específicas (contrato 1.4.0, SC-08).
 *
 * - Rebaja el precio solo si está activa, no está oculta y la fecha cae entre
 *   fecha_inicio y fecha_fin (ambas incluidas): se activa y desactiva sola.
 * - Precio con descuento a 2 decimales. Un precio fijo nunca sube el precio:
 *   si M3 bajó el precio normal por debajo del fijo, se cobra el normal.
 *
 * Aislamiento por comercio (RN-12) con PerteneceAComercio. Las lecturas
 * públicas (portal y precios para M5) se hacen en modo sistema.
 *
 * @property int $id_promocion
 * @property int $id_comercio
 * @property int $id_producto
 * @property string $titulo
 * @property string|null $descripcion
 * @property string $tipo
 * @property string $valor
 * @property string $alcance
 * @property CarbonInterface $fecha_inicio
 * @property CarbonInterface $fecha_fin
 * @property bool $activa
 * @property bool $oculta
 * @property string|null $motivo_ocultamiento
 * @property int|null $orden
 * @property int $id_usuario_crea
 * @property Collection<int, PromocionVariacion> $variaciones
 */
class Promocion extends Model
{
    use PerteneceAComercio;

    public const TIPO_PORCENTAJE = 'porcentaje';

    public const TIPO_PRECIO_FIJO = 'precio_fijo';

    public const ALCANCE_PRODUCTO = 'producto';

    public const ALCANCE_VARIACIONES = 'variaciones';

    public const ESTADO_PROGRAMADA = 'programada';

    public const ESTADO_VIGENTE = 'vigente';

    public const ESTADO_FINALIZADA = 'finalizada';

    public const ESTADO_DESACTIVADA = 'desactivada';

    public const ESTADO_OCULTA = 'oculta';

    /**
     * @var list<string>
     */
    public const ESTADOS = [
        self::ESTADO_PROGRAMADA,
        self::ESTADO_VIGENTE,
        self::ESTADO_FINALIZADA,
        self::ESTADO_DESACTIVADA,
        self::ESTADO_OCULTA,
    ];

    protected $table = 'promociones';

    protected $primaryKey = 'id_promocion';

    protected $fillable = [
        'id_comercio',
        'id_producto',
        'titulo',
        'descripcion',
        'tipo',
        'valor',
        'alcance',
        'fecha_inicio',
        'fecha_fin',
        'activa',
        'oculta',
        'motivo_ocultamiento',
        'orden',
        'id_usuario_crea',
    ];

    protected $attributes = [
        'activa' => true,
        'oculta' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_promocion' => 'integer',
            'id_comercio' => 'integer',
            'id_producto' => 'integer',
            'valor' => 'decimal:2',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
            'activa' => 'boolean',
            'oculta' => 'boolean',
            'orden' => 'integer',
            'id_usuario_crea' => 'integer',
        ];
    }

    /**
     * @return HasMany<PromocionVariacion, $this>
     */
    public function variaciones(): HasMany
    {
        return $this->hasMany(PromocionVariacion::class, 'id_promocion', 'id_promocion');
    }

    /**
     * Estado para mostrar: desactivada y oculta mandan sobre las fechas.
     */
    public function estado(DateTimeInterface $ahora): string
    {
        return match (true) {
            ! $this->activa => self::ESTADO_DESACTIVADA,
            $this->oculta => self::ESTADO_OCULTA,
            $this->fecha_inicio->greaterThan($ahora) => self::ESTADO_PROGRAMADA,
            $this->fecha_fin->lessThan($ahora) => self::ESTADO_FINALIZADA,
            default => self::ESTADO_VIGENTE,
        };
    }

    /**
     * Rebaja el precio en este momento.
     */
    public function rebajaPrecio(DateTimeInterface $ahora): bool
    {
        return $this->estado($ahora) === self::ESTADO_VIGENTE;
    }

    /**
     * @return list<int>
     */
    public function idsVariacion(): array
    {
        return $this->variaciones->pluck('id_variacion')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    public function aplicaA(int $idVariacion): bool
    {
        return $this->alcance === self::ALCANCE_PRODUCTO || in_array($idVariacion, $this->idsVariacion(), true);
    }

    /**
     * Precio con la promoción, a 2 decimales y nunca mayor que el normal.
     */
    public function precioCon(float $precioNormal): float
    {
        $precio = $this->tipo === self::TIPO_PORCENTAJE
            ? $precioNormal * (100 - (float) $this->valor) / 100
            : (float) $this->valor;

        return round(min($precio, $precioNormal), 2);
    }

    /**
     * Esquema Promocion del contrato.
     *
     * @return array<string, mixed>
     */
    public function aRespuesta(DateTimeInterface $ahora): array
    {
        return [
            'id_promocion' => $this->id_promocion,
            'id_comercio' => $this->id_comercio,
            'id_producto' => $this->id_producto,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'tipo' => $this->tipo,
            'valor' => (float) $this->valor,
            'alcance' => $this->alcance,
            'variaciones' => $this->alcance === self::ALCANCE_VARIACIONES ? $this->idsVariacion() : [],
            'fecha_inicio' => $this->fecha_inicio->toIso8601String(),
            'fecha_fin' => $this->fecha_fin->toIso8601String(),
            'estado' => $this->estado($ahora),
            'orden' => $this->orden,
            'motivo_ocultamiento' => $this->motivo_ocultamiento,
        ];
    }
}
