<?php

namespace App\Services\M1_Portal;

use App\Models\M1_Portal\CategoriaPortal;

/**
 * Lee la presentación del menú de categorías guardada en categorias_portal.
 */
class ConfiguracionMenuCategorias
{
    /**
     * Todas las filas, también las ocultas, por id_categoria.
     *
     * @return array<int, array{orden: int, visible: bool, icono_url: string|null}>
     */
    public function filas(): array
    {
        return CategoriaPortal::query()
            ->get()
            ->mapWithKeys(fn (CategoriaPortal $fila): array => [$fila->id_categoria => [
                'orden' => $fila->orden_visualizacion,
                'visible' => $fila->visible_en_menu,
                'icono_url' => $fila->icono_url,
            ]])
            ->all();
    }
}
