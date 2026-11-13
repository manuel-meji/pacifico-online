<?php

namespace Tests\Doubles\M1;

use App\Contracts\M1_Portal\ComercialPortalContract;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;

/**
 * Doble de ComercialPortalContract (contrato M1 1.5.0, SC-08 y SC-11) para
 * quien liquida al comercio (M2 o M7) y para M5 (cupones). Responde como el
 * contrato describe, con datos fijos en memoria.
 *
 * Cargos de destacados:
 * - Comercio 34: dos cargos (7 días el 2026-11-05 y 15 días el 2026-11-20).
 * - Comercio 35: un cargo (30 días el 2026-11-10).
 * - Cualquier otro comercio: sin cargos.
 *
 * Cupones (el código no distingue mayúsculas):
 * - PACIFICO10 (id 1): global, 10 % sobre todo el subtotal.
 * - CEIBA3000 (id 2): del comercio 34, ₡3 000 fijos, compra mínima de ₡10 000
 *   en productos de ese comercio.
 * - VENCIDO (id 3): global, ya venció.
 * - Cualquier otro código: no disponible.
 * Cada cupón admite un uso por cliente; registrarUsoCupon los recuerda
 * mientras viva el doble.
 */
class ComercialPortalFake implements ComercialPortalContract
{
    public const COMERCIO_CON_CARGOS = 34;

    public const COMERCIO_CON_UN_CARGO = 35;

    public const CUPON_GLOBAL = 'PACIFICO10';

    public const CUPON_DE_COMERCIO = 'CEIBA3000';

    public const CUPON_VENCIDO = 'VENCIDO';

    public const ID_CUPON_GLOBAL = 1;

    public const ID_CUPON_DE_COMERCIO = 2;

    /**
     * Usos registrados: id_cupon → id_pedido → id_cliente.
     *
     * @var array<int, array<int, int>>
     */
    private array $usos = [];

    public function listarCargosDestacado(int $idComercio, string $desde, string $hasta): array
    {
        $errores = [];

        foreach (['desde' => $desde, 'hasta' => $hasta] as $campo => $valor) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) || ! checkdate((int) substr($valor, 5, 2), (int) substr($valor, 8, 2), (int) substr($valor, 0, 4))) {
                $errores[$campo] = 'Debe tener el formato AAAA-MM-DD.';
            }
        }

        if ($errores === [] && $hasta < $desde) {
            $errores['hasta'] = 'No puede ser anterior a desde.';
        }

        if ($errores !== []) {
            throw new DatosInvalidosException($errores);
        }

        return array_values(array_filter(
            self::cargos(),
            fn (array $cargo): bool => $cargo['id_comercio'] === $idComercio
                && substr($cargo['fecha_cargo'], 0, 10) >= $desde
                && substr($cargo['fecha_cargo'], 0, 10) <= $hasta,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cargos(): array
    {
        return [
            self::cargo(1, self::COMERCIO_CON_CARGOS, 501, 1520, 7, 15000.0, '2026-11-05T10:00:00+00:00', '2026-11-12T10:00:00+00:00'),
            self::cargo(2, self::COMERCIO_CON_UN_CARGO, 502, 1522, 30, 50000.0, '2026-11-10T09:30:00+00:00', '2026-12-10T09:30:00+00:00'),
            self::cargo(3, self::COMERCIO_CON_CARGOS, 503, 1521, 15, 25000.0, '2026-11-20T15:00:00+00:00', '2026-12-05T15:00:00+00:00'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cargo(int $id, int $idComercio, int $idSolicitud, int $idProducto, int $dias, float $monto, string $inicio, string $fin): array
    {
        return [
            'id_cargo_destacado' => $id,
            'id_comercio' => $idComercio,
            'id_solicitud_destacado' => $idSolicitud,
            'id_producto' => $idProducto,
            'periodo_dias' => $dias,
            'monto' => $monto,
            'moneda' => 'CRC',
            'fecha_cargo' => $inicio,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
        ];
    }

    public function validarCupon(string $codigo, int $idCliente, float $subtotal, array $idsComercios = [], array $subtotalesPorComercio = []): array
    {
        $errores = [];

        if (trim($codigo) === '' || mb_strlen(trim($codigo)) > 50) {
            $errores['codigo'] = 'Es obligatorio y debe tener entre 1 y 50 caracteres.';
        }
        if ($idCliente < 1) {
            $errores['id_cliente'] = 'Debe ser un entero mayor o igual a 1.';
        }
        if ($subtotal < 0) {
            $errores['subtotal'] = 'Debe ser un número mayor o igual a 0.';
        }
        if ($errores !== []) {
            throw new DatosInvalidosException($errores);
        }

        $codigo = mb_strtoupper(trim($codigo));

        if ($codigo === self::CUPON_VENCIDO) {
            return self::rechazo('vencido', 'El cupón ya venció.');
        }

        $idCupon = match ($codigo) {
            self::CUPON_GLOBAL => self::ID_CUPON_GLOBAL,
            self::CUPON_DE_COMERCIO => self::ID_CUPON_DE_COMERCIO,
            default => null,
        };

        if ($idCupon === null) {
            return self::rechazo('no_disponible', 'El cupón no existe o no está disponible.');
        }

        if (in_array($idCliente, $this->usos[$idCupon] ?? [], true)) {
            return self::rechazo('limite_cliente', 'Ya usó este cupón el máximo de veces permitido.');
        }

        if ($idCupon === self::ID_CUPON_GLOBAL) {
            return self::aplicado($idCupon, null, 'porcentaje', 10.0, round($subtotal * 0.10, 2), 'Cupón de 10 % aplicado.');
        }

        $comercios = array_values(array_unique([...$idsComercios, ...array_keys($subtotalesPorComercio)]));

        if (! in_array(self::COMERCIO_CON_CARGOS, $comercios, true)) {
            return self::rechazo('comercio_no_aplica', 'El cupón no aplica a los productos del carrito.');
        }

        if (array_key_exists(self::COMERCIO_CON_CARGOS, $subtotalesPorComercio)) {
            $base = (float) $subtotalesPorComercio[self::COMERCIO_CON_CARGOS];
        } elseif ($comercios === [self::COMERCIO_CON_CARGOS]) {
            $base = $subtotal;
        } else {
            return self::rechazo('requiere_subtotal_comercio', 'El cupón solo aplica a los productos de un comercio y falta el subtotal de ese comercio.');
        }

        if ($base < 10000) {
            return self::rechazo('monto_minimo', 'El cupón aplica a compras desde ₡10 000.');
        }

        return self::aplicado($idCupon, self::COMERCIO_CON_CARGOS, 'monto_fijo', 3000.0, round(min(3000.0, $base), 2), 'Cupón de ₡3 000 aplicado.');
    }

    public function registrarUsoCupon(int $idCupon, int $idCliente, int $idPedido): bool
    {
        if ($idCupon < 1 || $idCliente < 1 || $idPedido < 1) {
            throw new DatosInvalidosException(['id_cupon' => 'Los identificadores deben ser enteros mayores o iguales a 1.']);
        }

        if (! in_array($idCupon, [self::ID_CUPON_GLOBAL, self::ID_CUPON_DE_COMERCIO], true)) {
            return false;
        }

        if (isset($this->usos[$idCupon][$idPedido])) {
            return true;
        }

        if (in_array($idCliente, $this->usos[$idCupon] ?? [], true)) {
            return false;
        }

        $this->usos[$idCupon][$idPedido] = $idCliente;

        return true;
    }

    /**
     * @return array{valido: bool, id_cupon: int|null, id_comercio: int|null, tipo_descuento: string|null, valor: float|null, monto_descuento: float, motivo: string|null, mensaje: string}
     */
    private static function aplicado(int $idCupon, ?int $idComercio, string $tipo, float $valor, float $descuento, string $mensaje): array
    {
        return [
            'valido' => true,
            'id_cupon' => $idCupon,
            'id_comercio' => $idComercio,
            'tipo_descuento' => $tipo,
            'valor' => $valor,
            'monto_descuento' => $descuento,
            'motivo' => null,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * @return array{valido: bool, id_cupon: int|null, id_comercio: int|null, tipo_descuento: string|null, valor: float|null, monto_descuento: float, motivo: string|null, mensaje: string}
     */
    private static function rechazo(string $motivo, string $mensaje): array
    {
        return [
            'valido' => false,
            'id_cupon' => null,
            'id_comercio' => null,
            'tipo_descuento' => null,
            'valor' => null,
            'monto_descuento' => 0.0,
            'motivo' => $motivo,
            'mensaje' => $mensaje,
        ];
    }
}
