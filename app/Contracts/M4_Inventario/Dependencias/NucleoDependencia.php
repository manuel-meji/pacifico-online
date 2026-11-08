<?php

namespace App\Contracts\M4_Inventario\Dependencias;

use Closure;

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
     * Usuario autenticado que ejecuta la operación; 0 si la ejecuta un proceso del
     * sistema (tarea programada, listener sin sesión o llamada interna de otro módulo).
     */
    public function usuarioActual(): int;

    /**
     * Ejecuta una operación de M4 dentro del ámbito de aislamiento del núcleo (RN-12).
     *
     * Con sesión de comercio, el Global Scope del núcleo (PerteneceAComercio) filtra
     * todas las consultas y escrituras de M4 por ese comercio. Sin sesión de comercio
     * (M1, M5 y M7 llamando por la interfaz PHP, listeners y la tarea programada)
     * la operación corre en el modo sistema del núcleo y M4 filtra por el
     * id_comercio que recibe en el contrato.
     *
     * Las consultas públicas del contrato (disponibilidad, que usan M1 y el portal)
     * no dependen de la sesión: con $publica = true siempre corren en modo sistema.
     *
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function ejecutarEnAmbito(Closure $operacion, bool $publica = false): mixed;

    /**
     * Registra una operación sensible en la bitácora de auditoría (sección 5.4).
     *
     * @param  array<string, mixed>  $detalles
     */
    public function registrarAuditoria(string $accion, string $entidad, int $entidadId, array $detalles = []): void;
}
