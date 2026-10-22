<?php

namespace App\Contracts\M4_Inventario\Dependencias;

/**
 * Lo que M4 necesita del núcleo compartido (sección 5 del PC3).
 * Se resuelve con el doble o con el núcleo real según config/integraciones.php, clave m4.nucleo.
 */
interface NucleoDependencia
{
    /**
     * Comercio de la sesión actual (RN-12); null si no hay sesión de comercio.
     */
    public function comercioActual(): ?int;

    /**
     * Registra una operación sensible en la bitácora de auditoría (sección 5.4).
     *
     * @param  array<string, mixed>  $detalles
     */
    public function registrarAuditoria(string $accion, string $entidad, int $entidadId, array $detalles = []): void;
}
