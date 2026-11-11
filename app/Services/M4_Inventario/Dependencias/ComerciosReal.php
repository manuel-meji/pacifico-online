<?php

namespace App\Services\M4_Inventario\Dependencias;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Throwable;

/**
 * M2 real, llamado dentro del mismo proceso por su interfaz PHP (sección 5.1
 * del PC3). Traducción de errores de M2 (figura 4.7b):
 *  - ERR-004 (el comercio no existe): para M4 es un comercio no habilitado, sin sedes.
 *  - cualquier otra falla: ERR-006 (M2 no disponible).
 */
class ComerciosReal implements ComerciosDependencia
{
    public function __construct(private readonly ComerciosServiceInterface $comercios) {}

    public function obtenerComercioActivo(int $idComercio): array
    {
        return $this->llamar('obtenerComercioActivo', $idComercio) ?? [
            'id_comercio' => $idComercio,
            'estado' => 'Inexistente',
            'activo' => false,
            'motivo_estado' => 'El comercio no existe en M2.',
        ];
    }

    public function obtenerSedesComercio(int $idComercio): array
    {
        return $this->llamar('obtenerSedesComercio', $idComercio) ?? ['id_comercio' => $idComercio, 'sedes' => []];
    }

    /**
     * @return array<string, mixed>|null null si M2 responde ERR-004 (el comercio no existe).
     */
    private function llamar(string $operacion, int $idComercio): ?array
    {
        if (! method_exists($this->comercios, $operacion)) {
            throw ErrorInventarioException::dependenciaNoDisponible('M2', "M2 aún no publica {$operacion} en ComerciosServiceInterface.");
        }

        try {
            return $this->comercios->{$operacion}($idComercio);
        } catch (ErrorInventarioException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($this->codigo($e) === 'ERR-004') {
                return null;
            }

            throw ErrorInventarioException::dependenciaNoDisponible('M2');
        }
    }

    /** Código de error estándar del proveedor (ErrorEstandarInterface del núcleo o propiedad codigo). */
    private function codigo(Throwable $e): ?string
    {
        if (method_exists($e, 'getCodigo')) {
            return $e->getCodigo();
        }

        return property_exists($e, 'codigo') ? (string) $e->codigo : null;
    }
}
