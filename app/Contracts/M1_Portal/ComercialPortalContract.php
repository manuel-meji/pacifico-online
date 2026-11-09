<?php

namespace App\Contracts\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;

/**
 * Interfaz PHP de M1 para la parte comercial del portal (contrato M1 1.4.0,
 * SC-08, x-interfaz-php-comercial): lo que otros módulos consultan desde su
 * backend sobre destacados y, más adelante, promociones.
 *
 * Va aparte de PortalPublicoContract para no obligar a quienes ya la
 * implementan (por ejemplo, el doble de M5) a agregar métodos: los
 * consumidores actuales de PortalPublicoContract no cambian.
 *
 * Doble de prueba: tests/Doubles/M1/ComercialPortalFake.php.
 */
interface ComercialPortalContract
{
    /**
     * Cargos por productos destacados pagados de un comercio, cuya fecha de
     * cargo cae entre $desde y $hasta (ambos incluidos, formato AAAA-MM-DD),
     * ordenados por fecha de cargo. Quien liquida al comercio los descuenta
     * del monto a pagarle. Los destacados que pone la administración no
     * generan cargo.
     *
     * Consumidores: M2 o M7 (liquidación al comercio).
     *
     * @return list<array{id_cargo_destacado: int, id_comercio: int, id_solicitud_destacado: int, id_producto: int, periodo_dias: int, monto: float, moneda: string, fecha_cargo: string, fecha_inicio: string, fecha_fin: string}> Esquema CargoDestacado.
     *
     * @throws DatosInvalidosException ERR-005 si una fecha no tiene el formato o $desde es posterior a $hasta.
     */
    public function listarCargosDestacado(int $idComercio, string $desde, string $hasta): array;
}
