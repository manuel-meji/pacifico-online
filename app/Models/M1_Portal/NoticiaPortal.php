<?php

namespace App\Models\M1_Portal;

use App\Services\M1_Portal\DTO\NoticiaDTO;
use Carbon\CarbonInterface;
use Database\Factories\M1_Portal\NoticiaPortalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabla noticias_portal: noticias o avisos de la administración (borrador,
 * publicada o archivada). id_usuario_autor es una clave compartida con el núcleo.
 *
 * La migración define el periodo de publicación con fecha_inicio y fecha_fin
 * (el diagrama del PC3 tenía fecha_publicacion); aDTO usa fecha_inicio como
 * fechaPublicacion.
 *
 * @property int $id_noticia_portal
 * @property int $id_usuario_autor
 * @property string $titulo_noticia
 * @property string $slug_noticia
 * @property string $resumen_noticia
 * @property string $contenido_noticia
 * @property string|null $imagen_url
 * @property string $estado_noticia
 * @property CarbonInterface $fecha_inicio
 * @property CarbonInterface $fecha_fin
 */
class NoticiaPortal extends Model
{
    /** @use HasFactory<NoticiaPortalFactory> */
    use HasFactory;

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_PUBLICADA = 'publicada';

    public const ESTADO_ARCHIVADA = 'archivada';

    protected $table = 'noticias_portal';

    protected $primaryKey = 'id_noticia_portal';

    protected $fillable = [
        'id_usuario_autor',
        'titulo_noticia',
        'slug_noticia',
        'resumen_noticia',
        'contenido_noticia',
        'imagen_url',
        'estado_noticia',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $attributes = [
        'estado_noticia' => self::ESTADO_BORRADOR,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_noticia_portal' => 'integer',
            'id_usuario_autor' => 'integer',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
        ];
    }

    /**
     * Publicada si su estado es publicada y hoy cae dentro de su periodo de
     * publicación (fecha_inicio y fecha_fin incluidos).
     */
    public function estaPublicada(): bool
    {
        $ahora = now();

        return $this->estado_noticia === self::ESTADO_PUBLICADA
            && $this->fecha_inicio->lessThanOrEqualTo($ahora)
            && $this->fecha_fin->greaterThanOrEqualTo($ahora);
    }

    public function aDTO(): NoticiaDTO
    {
        return new NoticiaDTO(
            idNoticia: $this->id_noticia_portal,
            titulo: $this->titulo_noticia,
            slug: $this->slug_noticia,
            resumen: $this->resumen_noticia,
            contenido: $this->contenido_noticia,
            imagenUrl: $this->imagen_url,
            estado: $this->estado_noticia,
            fechaPublicacion: $this->fecha_inicio,
        );
    }
}
