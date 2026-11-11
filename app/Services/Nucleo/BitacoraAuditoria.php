<?php

declare(strict_types=1);

namespace App\Services\Nucleo;

use App\Models\Nucleo\RegistroAuditoria;
use Illuminate\Support\Facades\Auth;

/**
 * Servicio central para registrar operaciones sensibles en la bitácora
 * de auditoría del Núcleo.
 *
 * Ningún módulo debería escribir directamente en bitacora_auditoria.
 * Los módulos consumen NucleoServiceInterface y el Núcleo delega aquí.
 */
class BitacoraAuditoria
{
    public function __construct(private readonly ContextoComercio $contextoComercio) {}

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
     * @param  array<string, mixed>  $detalles
     */
    public function registrar(
        string $accion,
        string $entidad,
        int $entidadId,
        array $detalles = []
    ): void {
        $usuario = Auth::user();

        $idComercio = $this->contextoComercio->idActual();

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

        RegistroAuditoria::create([
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
