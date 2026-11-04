<?php

namespace Database\Factories\M1_Portal;

use App\Models\M1_Portal\NoticiaPortal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Datos de prueba de NoticiaPortal que cumplen las restricciones de su migración.
 *
 * @extends Factory<NoticiaPortal>
 */
class NoticiaPortalFactory extends Factory
{
    protected $model = NoticiaPortal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_usuario_autor' => fake()->numberBetween(1, 1000),
            'titulo_noticia' => $titulo = fake()->sentence(5),
            'slug_noticia' => Str::slug($titulo).'-'.fake()->unique()->numberBetween(1, 99999),
            'resumen_noticia' => fake()->sentence(12),
            'contenido_noticia' => fake()->paragraphs(3, true),
            'imagen_url' => null,
            'estado_noticia' => NoticiaPortal::ESTADO_PUBLICADA,
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addMonth(),
        ];
    }
}
