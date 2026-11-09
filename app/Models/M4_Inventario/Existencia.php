<?php

namespace App\Models\M4_Inventario;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Database\Factories\M4_Inventario\ExistenciaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Existencia de una variación en una ubicación (tabla existencias, sección 6 del PC3).
 *
 * PerteneceAComercio es el Global Scope del núcleo (RN-12): con sesión de un
 * comercio solo se ven y escriben sus filas. disponible = física − reservada; no
 * se maneja stock defectuoso (SC-06).
 *
 * @property int $id_existencia
 * @property int $id_comercio
 * @property int $id_producto
 * @property int $id_variacion
 * @property int $id_ubicacion
 * @property int $cantidad_fisica
 * @property int $cantidad_reservada
 * @property int $stock_minimo
 * @property float $costo_promedio
 * @property Carbon|null $updated_at
 */
class Existencia extends Model
{
    /** @use HasFactory<ExistenciaFactory> */
    use HasFactory;

    use PerteneceAComercio;

    protected $table = 'existencias';

    protected $primaryKey = 'id_existencia';

    protected $guarded = ['id_existencia'];

    protected $attributes = [
        'cantidad_fisica' => 0,
        'cantidad_reservada' => 0,
        'stock_minimo' => 0,
        'costo_promedio' => 0,
    ];

    protected function casts(): array
    {
        return [
            'id_comercio' => 'integer',
            'id_producto' => 'integer',
            'id_variacion' => 'integer',
            'id_ubicacion' => 'integer',
            'cantidad_fisica' => 'integer',
            'cantidad_reservada' => 'integer',
            'stock_minimo' => 'integer',
            'costo_promedio' => 'float',
        ];
    }

    protected static function newFactory(): ExistenciaFactory
    {
        return ExistenciaFactory::new();
    }

    /** @return HasMany<Reserva, $this> */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'id_existencia', 'id_existencia');
    }

    public function disponible(): int
    {
        return $this->cantidad_fisica - $this->cantidad_reservada;
    }

    /** RN-M4-004: disponible en o bajo el mínimo configurado (sin mínimo no hay alerta). */
    public function bajoMinimo(): bool
    {
        return $this->stock_minimo > 0 && $this->disponible() <= $this->stock_minimo;
    }

    /** Entrada física: suma unidades y fija el costo promedio que calculó la estrategia. */
    public function aplicarEntrada(int $cantidad, float $costoPromedio): void
    {
        $this->cantidad_fisica += $cantidad;
        $this->costo_promedio = $costoPromedio;
    }

    /**
     * Salida física manual o ajuste a la baja. No puede tocar unidades comprometidas
     * por reservas ACTIVA o CONFIRMADA (ERR-M4-001).
     */
    public function disminuirFisica(int $cantidad): void
    {
        $this->verificarSuficiente($cantidad);
        $this->cantidad_fisica -= $cantidad;
    }

    /** Reserva: compromete unidades disponibles sin tocar el físico (ERR-M4-001 si no alcanzan). */
    public function comprometer(int $cantidad): void
    {
        $this->verificarSuficiente($cantidad);
        $this->cantidad_reservada += $cantidad;
    }

    /** Liberación o vencimiento: devuelve las unidades comprometidas al disponible. */
    public function liberarCompromiso(int $cantidad): void
    {
        $this->cantidad_reservada -= $cantidad;
    }

    /** Salida por despacho: descuenta físico y reservado en igual medida (el disponible no cambia). */
    public function consumirCompromiso(int $cantidad): void
    {
        $this->cantidad_reservada -= $cantidad;
        $this->cantidad_fisica -= $cantidad;
    }

    private function verificarSuficiente(int $cantidad): void
    {
        if ($this->disponible() < $cantidad) {
            throw ErrorInventarioException::existenciaInsuficiente();
        }
    }

    public function valorInventario(): float
    {
        return round($this->cantidad_fisica * $this->costo_promedio, 2);
    }

    /**
     * Esquema Existencia del contrato.
     *
     * @return array<string, mixed>
     */
    public function aContrato(?string $sku = null): array
    {
        // sku y fecha_actualizacion son opcionales y no anulables en el contrato: si M3 no
        // devolvió el SKU o la fila nunca se actualizó, el campo no se envía (DEF-M4-07).
        return array_filter([
            'id_existencia' => $this->id_existencia,
            'id_comercio' => $this->id_comercio,
            'id_producto' => $this->id_producto,
            'id_variacion' => $this->id_variacion,
            'id_ubicacion' => $this->id_ubicacion,
            'sku' => $sku,
            'cantidad_fisica' => $this->cantidad_fisica,
            'cantidad_reservada' => $this->cantidad_reservada,
            'cantidad_disponible' => $this->disponible(),
            'stock_minimo' => $this->stock_minimo,
            'bajo_minimo' => $this->bajoMinimo(),
            'fecha_actualizacion' => $this->updated_at?->toIso8601String(),
            'valor_inventario' => $this->valorInventario(),
        ], fn (mixed $valor, string $campo): bool => $valor !== null || ! in_array($campo, ['sku', 'fecha_actualizacion'], true), ARRAY_FILTER_USE_BOTH);
    }
}
