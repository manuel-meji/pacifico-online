<?php

declare(strict_types=1);

namespace App\Models\M2_Comercios;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Comercio: entidad canónica cuyo propietario es M2 (modelo de datos, sección 6).
 *
 * Es la raíz del aislamiento: su llave id_comercio es la que usan los demás
 * módulos como referencia lógica. Por eso no lleva PerteneceAComercio.
 */
class Comercio extends Model
{
    public const PENDIENTE = 'Pendiente';

    public const ACTIVO = 'Activo';

    public const RECHAZADO = 'Rechazado';

    public const SUSPENDIDO = 'Suspendido';

    public const ESTADOS = [self::PENDIENTE, self::ACTIVO, self::RECHAZADO, self::SUSPENDIDO];

    protected $table = 'comercios';

    protected $primaryKey = 'id_comercio';

    protected $guarded = ['id_comercio'];

    protected $casts = ['inscrito_como_contribuyente' => 'boolean'];

    public function estaActivo(): bool
    {
        return $this->estado === self::ACTIVO;
    }

    public function estaPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    public function estaSuspendido(): bool
    {
        return $this->estado === self::SUSPENDIDO;
    }

    /** Comercio habilitado (glosario): estado Activo. */
    public function scopeHabilitados(Builder $consulta): Builder
    {
        return $consulta->where('estado', self::ACTIVO);
    }

    /** Destacado (RF-011, RF-021): Activo con una publicidad vigente hoy. */
    public function scopeDestacados(Builder $consulta): Builder
    {
        return $consulta->whereHas('publicidades', fn (Builder $p) => $p->vigentes());
    }

    /** Motivo del estado actual: suspensión abierta (RN-005) o último rechazo (RN-016). */
    public function motivoEstado(): ?string
    {
        return match ($this->estado) {
            self::SUSPENDIDO => $this->suspensiones()
                ->whereNull('fecha_reactivacion')
                ->latest('fecha_suspension')
                ->value('motivo'),
            self::RECHAZADO => $this->solicitudes()
                ->where('estado', SolicitudComercio::RECHAZADA)
                ->latest('updated_at')
                ->value('motivo_rechazo'),
            default => null,
        };
    }

    public function micrositio(): HasOne
    {
        return $this->hasOne(Micrositio::class, 'id_comercio', 'id_comercio');
    }

    public function banners(): HasMany
    {
        return $this->hasMany(BannerComercio::class, 'id_comercio', 'id_comercio');
    }

    public function galeria(): HasMany
    {
        return $this->hasMany(ImagenGaleriaComercio::class, 'id_comercio', 'id_comercio');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioComercio::class, 'id_comercio', 'id_comercio');
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(ContactoComercio::class, 'id_comercio', 'id_comercio');
    }

    public function politicas(): HasMany
    {
        return $this->hasMany(PoliticaComercio::class, 'id_comercio', 'id_comercio');
    }

    public function publicidades(): HasMany
    {
        return $this->hasMany(PublicidadComercio::class, 'id_comercio', 'id_comercio');
    }

    public function suspensiones(): HasMany
    {
        return $this->hasMany(SuspensionComercio::class, 'id_comercio', 'id_comercio');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudComercio::class, 'id_comercio', 'id_comercio');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoComercio::class, 'id_comercio', 'id_comercio');
    }

    public function comisiones(): HasMany
    {
        return $this->hasMany(ComisionComercio::class, 'id_comercio', 'id_comercio');
    }

    public function membresias(): HasMany
    {
        return $this->hasMany(MembresiaComercio::class, 'id_comercio', 'id_comercio');
    }
}
