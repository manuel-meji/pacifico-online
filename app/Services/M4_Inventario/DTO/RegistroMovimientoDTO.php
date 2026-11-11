<?php

namespace App\Services\M4_Inventario\DTO;

use App\Enums\M4_Inventario\TipoMovimiento;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Solicitud de entrada o salida manual (MovimientoSolicitud del contrato).
 */
final readonly class RegistroMovimientoDTO
{
    public function __construct(
        public int $idComercio,
        public int $idVariacion,
        public int $idUbicacion,
        public TipoMovimiento $tipo,
        public int $cantidad,
        public ?float $costoUnitario,
        public string $justificacion,
    ) {}

    /**
     * Valida la forma de MovimientoSolicitud (ERR-005 con el detalle por campo).
     *
     * @param  array<string, mixed>  $solicitud
     */
    public static function desdeSolicitud(array $solicitud): self
    {
        $errores = [];

        foreach (['id_comercio', 'id_variacion', 'id_ubicacion', 'cantidad'] as $campo) {
            if (! is_int($solicitud[$campo] ?? null) || $solicitud[$campo] < 1) {
                $errores[$campo] = 'Debe ser un entero mayor o igual a 1.';
            }
        }

        $tipo = TipoMovimiento::tryFrom((string) ($solicitud['tipo_movimiento'] ?? ''));

        if (! in_array($tipo, [TipoMovimiento::Entrada, TipoMovimiento::Salida], true)) {
            $errores['tipo_movimiento'] = 'Debe ser entrada o salida.';
        }

        $costo = $solicitud['costo_unitario'] ?? null;

        if ($tipo === TipoMovimiento::Entrada && (! is_numeric($costo) || (float) $costo < 0)) {
            $errores['costo_unitario'] = 'Es obligatorio en las entradas y no puede ser negativo.';
        }

        $justificacion = trim((string) ($solicitud['justificacion'] ?? ''));

        if ($justificacion === '' || mb_strlen($justificacion) > 255) {
            $errores['justificacion'] = 'Es obligatoria y admite hasta 255 caracteres.';
        }

        if ($errores !== []) {
            throw ErrorInventarioException::datosInvalidos($errores);
        }

        return new self(
            $solicitud['id_comercio'],
            $solicitud['id_variacion'],
            $solicitud['id_ubicacion'],
            $tipo,
            $solicitud['cantidad'],
            $tipo === TipoMovimiento::Entrada ? round((float) $costo, 4) : null,
            $justificacion,
        );
    }
}
