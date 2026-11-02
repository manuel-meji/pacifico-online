<?php

declare(strict_types=1);

namespace App\Services\Nucleo;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Servicio central para registrar operaciones sensibles en la bitácora
 * de auditoría del Núcleo.
 *
 * Ningún módulo debería escribir directamente en bitacora_auditoria.
 * Los módulos consumen NucleoServiceInterface y el Núcleo delega aquí.
 */
class BitacoraAuditoria
{
    /**
     * Registra una operación en la bitácora.
     *
     * IMPORTANTE:
     * Actualmente entidad_id es BIGINT en la base de datos.
     * Existe un conflicto con módulos que manejan UUID, por ejemplo
     * reservas de M4. No modificar la migración existente hasta que
     * el Comité defina si entidad_id debe convertirse a texto/UUID.
     *
     * TODO COMITÉ:
     * Definir cómo identificar el módulo que origina la auditoría.
     * NucleoServiceInterface actualmente no recibe $modulo como
     * parámetro explícito.
     *
     * @param array<string, mixed> $detalles
     */
    public function registrar(
        string $accion,
        string $entidad,
        int $entidadId,
        array $detalles = []
    ): void {
        $usuario = Auth::user();

        /*
         * TODO INTEGRACIÓN:
         * Confirmar con el equipo del Núcleo si el comercio debe obtenerse
         * siempre del usuario autenticado, de la sesión o del contexto
         * definido por Persona 6 (EstablecerComercio).
         */
        $idComercio = $usuario?->id_comercio
            ?? session('comercio_id');

        /*
         * TODO CONTRATO:
         * El campo modulo es obligatorio en bitacora_auditoria, pero la
         * interfaz actual no lo recibe explícitamente.
         *
         * Temporalmente se admite que venga dentro de $detalles.
         * No debe dejarse 'NUCLEO' como valor definitivo para operaciones
         * originadas por M1-M7.
         */
        $modulo = $detalles['modulo'] ?? 'NUCLEO';

        DB::table('bitacora_auditoria')->insert([
            'id_usuario' => $usuario?->id_usuario,
            'id_comercio' => $idComercio,
            'fecha_hora' => now(),
            'accion' => $accion,
            'modulo' => $modulo,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'resultado' => $detalles['resultado'] ?? 'exitoso',
            'motivo' => $detalles['motivo'] ?? null,
        ]);
    }
}