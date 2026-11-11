<?php

namespace App\Contracts\M4_Inventario\Dependencias;

use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Lo que M4 necesita del núcleo para verificar la autorización previa del Administrador del
 * comercio en ajustes (RN-M4-011) y transferencias (RN-M4-012).
 *
 * Supuesto del PC3: el núcleo aún no publica el flujo de solicitud y aprobación
 * (x-acuerdos-pendientes del contrato M4 v1.2.0). Mientras tanto se prueba con
 * tests/Doubles/M4/AutorizacionesFake; cuando el núcleo lo publique, se agrega su adaptador
 * real y la clave m4.autorizaciones en config/integraciones.php, igual que las demás dependencias.
 */
interface VerificadorAutorizacionesInterface
{
    public const OPERACION_AJUSTE = 'ajuste';

    public const OPERACION_TRANSFERENCIA = 'transferencia';

    /**
     * Verifica que la autorización exista, esté vigente, la haya emitido un Administrador del
     * mismo comercio y corresponda a la operación, la variación, las ubicaciones y la cantidad.
     *
     * @param  list<int>  $idsUbicacion  Origen y destino en una transferencia; una sola en un ajuste.
     * @return array{id_autorizacion: string, id_autorizador: int, fecha_autorizacion: string}
     *
     * @throws ErrorInventarioException ERR-002 (403) si la autorización no es válida.
     */
    public function verificar(string $idAutorizacion, string $operacion, int $idComercio, int $idVariacion, array $idsUbicacion, int $cantidad): array;

    /**
     * Marca la autorización como usada para que no se aplique dos veces.
     */
    public function marcarUsada(string $idAutorizacion): void;
}
