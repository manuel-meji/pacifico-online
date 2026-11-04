<?php

namespace App\Models\M1_Portal;

use Carbon\CarbonInterface;
use Database\Factories\M1_Portal\BusquedaUsuarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla busquedas_usuario: registro de cada búsqueda del portal. id_cliente es
 * nulo si la persona no inició sesión. La tabla no tiene created_at ni
 * updated_at; la fecha la guarda fecha_busqueda.
 *
 * @property int $id_busqueda_usuario
 * @property int|null $id_cliente
 * @property string|null $identificador_sesion
 * @property string $termino_busqueda
 * @property int $cantidad_resultados
 * @property CarbonInterface $fecha_busqueda
 */
class BusquedaUsuario extends Model
{
    /** @use HasFactory<BusquedaUsuarioFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'busquedas_usuario';

    protected $primaryKey = 'id_busqueda_usuario';

    protected $fillable = [
        'id_cliente',
        'identificador_sesion',
        'termino_busqueda',
        'cantidad_resultados',
        'fecha_busqueda',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_busqueda_usuario' => 'integer',
            'id_cliente' => 'integer',
            'cantidad_resultados' => 'integer',
            'fecha_busqueda' => 'datetime',
        ];
    }

    /**
     * Composición con FiltroAplicado (1 a 0..*).
     *
     * @return HasMany<FiltroAplicado, $this>
     */
    public function filtros(): HasMany
    {
        return $this->hasMany(FiltroAplicado::class, 'id_busqueda_usuario', 'id_busqueda_usuario');
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }
}
