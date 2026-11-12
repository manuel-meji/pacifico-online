<?php

namespace App\Contracts\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;

/**
 * Interfaz PHP de M1 para la parte comercial del portal (contrato M1 1.5.0,
 * SC-08 y SC-11, x-interfaz-php-comercial): lo que otros módulos consultan
 * desde su backend sobre destacados (1.4.0) y cupones de descuento (1.5.0).
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

    /**
     * Indica si un cupón de descuento aplica al carrito y cuánto descuenta
     * (desde 1.5.0, SC-11). No registra el uso: para eso está
     * registrarUsoCupon, que M5 llama al confirmar el pedido.
     *
     * - Un cupón global de la plataforma descuenta sobre $subtotal.
     * - Un cupón de un comercio descuenta solo sobre el subtotal de ese
     *   comercio: el de $subtotalesPorComercio o, si el carrito es de un solo
     *   comercio, $subtotal. En un carrito de varios comercios sin ese
     *   desglose responde valido=false con motivo requiere_subtotal_comercio.
     * - $subtotal y los subtotales por comercio van sin envío ni impuesto, con
     *   los precios de promoción ya aplicados.
     * - monto_descuento va a 2 decimales y nunca supera la base.
     * - Un cupón que no aplica no lanza excepción: responde valido=false con
     *   motivo (no_disponible, no_iniciado, vencido, agotado, limite_cliente,
     *   comercio_no_aplica, requiere_subtotal_comercio, monto_minimo o
     *   sin_descuento) y un mensaje para mostrar a la persona compradora.
     *
     * Consumidor: M5 (checkout y confirmación del pedido). Sin sesión de usuario.
     *
     * @param  string  $codigo  Código del cupón; no distingue mayúsculas.
     * @param  int  $idCliente  Cliente que quiere usar el cupón.
     * @param  float  $subtotal  Subtotal del carrito, sin envío ni impuesto.
     * @param  list<int>  $idsComercios  Comercios presentes en el carrito.
     * @param  array<int, float|int>  $subtotalesPorComercio  Subtotal de cada comercio del carrito, por id_comercio.
     * @return array{valido: bool, id_cupon: int|null, id_comercio: int|null, tipo_descuento: string|null, valor: float|null, monto_descuento: float, motivo: string|null, mensaje: string} Esquema ResultadoCupon.
     *
     * @throws DatosInvalidosException ERR-005 si un parámetro no tiene el formato.
     */
    public function validarCupon(string $codigo, int $idCliente, float $subtotal, array $idsComercios = [], array $subtotalesPorComercio = []): array;

    /**
     * Registra el uso de un cupón en un pedido confirmado y suma el uso
     * (desde 1.5.0, SC-11). Es idempotente por pedido: repetirlo con el mismo
     * $idPedido responde true sin sumar otro uso.
     *
     * Responde false si el cupón no existe o ya no se puede usar (desactivado,
     * fuera de fechas, agotado o límite del cliente); en ese caso M5 no debe
     * aplicar el descuento.
     *
     * Consumidor: M5, dentro de la confirmación del pedido.
     *
     * @throws DatosInvalidosException ERR-005 si un identificador es menor que 1.
     */
    public function registrarUsoCupon(int $idCupon, int $idCliente, int $idPedido): bool;
}
