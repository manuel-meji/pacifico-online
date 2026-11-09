<?php

declare(strict_types=1);

namespace App\Services\M3_Catalogo;

use InvalidArgumentException;

/**
 * Calcula el precio final por unidad de una variación, aplicando el descuento.
 *
 * Clase pura: no usa base de datos ni modelos. Usa bcmath (sin float) para que
 * valores como 7 641,50 no salgan 7 641,499999. El IVA no se calcula aquí: lo
 * calcula M5 sobre el precio final.
 */
final class CalculadoraPrecioFinal
{
    public const DESCUENTO_NINGUNO    = 'ninguno';
    public const DESCUENTO_PORCENTAJE = 'porcentaje';
    public const DESCUENTO_MONTO_FIJO = 'monto_fijo';

    /** Escala interna para no perder decimales antes de redondear. */
    private const ESCALA_INTERNA = 10;

    /**
     * @param  string|int|float       $precio          Precio unitario (>= 0).
     * @param  string|null            $tipoDescuento   ninguno | porcentaje | monto_fijo
     * @param  string|int|float|null  $valorDescuento  Porcentaje (0-100) o monto por unidad.
     * @return string                 Precio final con 2 decimales, por ejemplo "7641.50".
     */
    public function calcular(
        string|int|float $precio,
        ?string $tipoDescuento = self::DESCUENTO_NINGUNO,
        string|int|float|null $valorDescuento = null
    ): string {
        $precio = $this->normalizar($precio, 'El precio');

        if (bccomp($precio, '0', self::ESCALA_INTERNA) < 0) {
            throw new InvalidArgumentException('El precio no puede ser negativo.');
        }

        $tipo = $tipoDescuento ?? self::DESCUENTO_NINGUNO;

        $final = match ($tipo) {
            self::DESCUENTO_NINGUNO    => $precio,
            self::DESCUENTO_PORCENTAJE => $this->aplicarPorcentaje($precio, $valorDescuento),
            self::DESCUENTO_MONTO_FIJO => $this->aplicarMontoFijo($precio, $valorDescuento),
            default => throw new InvalidArgumentException(
                'El tipo de descuento debe ser ninguno, porcentaje o monto_fijo.'
            ),
        };

        // Nunca menor que 0.
        if (bccomp($final, '0', self::ESCALA_INTERNA) < 0) {
            $final = '0';
        }

        return $this->redondear($final);
    }

    private function aplicarPorcentaje(string $precio, string|int|float|null $valor): string
    {
        $porcentaje = $this->normalizar($valor, 'El porcentaje de descuento');

        if (bccomp($porcentaje, '0', self::ESCALA_INTERNA) < 0
            || bccomp($porcentaje, '100', self::ESCALA_INTERNA) > 0) {
            throw new InvalidArgumentException('El porcentaje de descuento debe estar entre 0 y 100.');
        }

        // precio × (1 − valor/100)  ==  precio × (100 − valor) / 100
        $factor = bcsub('100', $porcentaje, self::ESCALA_INTERNA);

        return bcdiv(bcmul($precio, $factor, self::ESCALA_INTERNA), '100', self::ESCALA_INTERNA);
    }

    private function aplicarMontoFijo(string $precio, string|int|float|null $valor): string
    {
        $monto = $this->normalizar($valor, 'El monto de descuento');

        if (bccomp($monto, '0', self::ESCALA_INTERNA) < 0) {
            throw new InvalidArgumentException('El monto de descuento no puede ser negativo.');
        }

        return bcsub($precio, $monto, self::ESCALA_INTERNA);
    }

    /** Redondeo a 2 decimales, mitad hacia arriba (valores >= 0). */
    private function redondear(string $valor): string
    {
        return bcadd(bcadd($valor, '0.005', self::ESCALA_INTERNA), '0', 2);
    }

    /** Convierte la entrada a texto numérico validado, sin pasar por cálculos con float. */
    private function normalizar(string|int|float|null $valor, string $nombre): string
    {
        if ($valor === null || (is_string($valor) && ! is_numeric($valor))) {
            throw new InvalidArgumentException("$nombre debe ser un número.");
        }

        if (is_float($valor)) {
            return number_format($valor, self::ESCALA_INTERNA, '.', '');
        }

        return (string) $valor;
    }
}