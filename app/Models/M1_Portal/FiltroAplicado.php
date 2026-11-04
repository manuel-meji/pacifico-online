<?php

namespace App\Models\M1_Portal;

use Database\Factories\M1_Portal\FiltroAplicadoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla filtros_aplicados: filtros usados en cada búsqueda (1FN). La tabla no
 * tiene created_at ni updated_at.
 *
 * @property int $id_filtro_aplicado
 * @property int $id_busqueda_usuario
 * @property string $tipo_filtro
 * @property string $valor_filtro
 */
class FiltroAplicado extends Model
{
    /** @use HasFactory<FiltroAplicadoFactory> */
    use HasFactory;

    /**
     * Valores que admite chk_filtros_aplicados_tipo.
     *
     * @var list<string>
     */
    public const TIPOS = [
        'categoria', 'subcategoria', 'marca', 'comercio',
        'precio_minimo', 'precio_maximo', 'disponibilidad', 'orden',
    ];

    public $timestamps = false;

    protected $table = 'filtros_aplicados';

    protected $primaryKey = 'id_filtro_aplicado';

    protected $fillable = [
        'id_busqueda_usuario',
        'tipo_filtro',
        'valor_filtro',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_filtro_aplicado' => 'integer',
            'id_busqueda_usuario' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BusquedaUsuario, $this>
     */
    public function busqueda(): BelongsTo
    {
        return $this->belongsTo(BusquedaUsuario::class, 'id_busqueda_usuario', 'id_busqueda_usuario');
    }
}
