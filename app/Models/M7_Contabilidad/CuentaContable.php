<?php

namespace App\Models\M7_Contabilidad;

use App\Models\Nucleo\Concerns\PerteneceAComercio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Cuenta contable del plan de cuentas de un comercio.
 * Aislamiento por comercio: trait PerteneceAComercio del Núcleo (RN-12 / ADR-006).
 */
class CuentaContable extends Model
{
    use PerteneceAComercio;

    public const TIPOS = ['ACTIVO', 'PASIVO', 'PATRIMONIO', 'INGRESO', 'GASTO'];

    protected $table = 'cuentas_contables';

    protected $primaryKey = 'id_cuenta_contable';

    protected $fillable = [
        'id_comercio',
        'codigo',
        'nombre',
        'tipo',
        'nivel',
        'id_cuenta_padre',
        'activa',
    ];

    protected $casts = [
        'id_comercio' => 'integer',
        'nivel' => 'integer',
        'id_cuenta_padre' => 'integer',
        'activa' => 'boolean',
    ];

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_cuenta_padre', 'id_cuenta_contable');
    }

    public function subcuentas(): HasMany
    {
        return $this->hasMany(self::class, 'id_cuenta_padre', 'id_cuenta_contable');
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public static function naturalezaDeTipo(string $tipo): string
    {
        return in_array($tipo, ['ACTIVO', 'GASTO'], true) ? 'DEUDORA' : 'ACREEDORA';
    }

    public function getNaturalezaAttribute(): string
    {
        return self::naturalezaDeTipo((string) $this->tipo);
    }

    /**
     * Nota: DB::table no pasa por el FiltroComercioScope. Es seguro porque
     * la cuenta ($this) ya se obtuvo con el aislamiento aplicado.
     */
    public function tieneMovimientos(): bool
    {
        if (! $this->exists) {
            return false;
        }

        return DB::table('detalle_asientos')
            ->where('id_cuenta_contable', $this->getKey())
            ->exists();
    }
}