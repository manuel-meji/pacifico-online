<?php

namespace App\Services\M4_Inventario\DTO;

use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Transferencia entre ubicaciones (TransferenciaSolicitud del contrato).
 */
final readonly class TransferenciaDTO
{
    public function __construct(
        public int $idComercio,
        public int $idVariacion,
        public int $idUbicacionOrigen,
        public int $idUbicacionDestino,
        public int $cantidad,
        public string $justificacion,
        public string $idAutorizacion,
    ) {}

    /**
     * @param  array<string, mixed>  $solicitud
     */
    public static function desdeSolicitud(array $solicitud): self
    {
        $errores = [];

        foreach (['id_comercio', 'id_variacion', 'id_ubicacion_origen', 'id_ubicacion_destino', 'cantidad'] as $campo) {
            if (! is_int($solicitud[$campo] ?? null) || $solicitud[$campo] < 1) {
                $errores[$campo] = 'Debe ser un entero mayor o igual a 1.';
            }
        }

        if (! isset($errores['id_ubicacion_origen'], $errores['id_ubicacion_destino'])
            && ($solicitud['id_ubicacion_origen'] ?? null) === ($solicitud['id_ubicacion_destino'] ?? null)) {
            $errores['id_ubicacion_destino'] = 'El destino debe ser distinto del origen.';
        }

        $justificacion = trim((string) ($solicitud['justificacion'] ?? ''));

        if ($justificacion === '' || mb_strlen($justificacion) > 255) {
            $errores['justificacion'] = 'Es obligatoria y admite hasta 255 caracteres.';
        }

        $autorizacion = trim((string) ($solicitud['id_autorizacion'] ?? ''));

        if ($autorizacion === '') {
            $errores['id_autorizacion'] = 'La transferencia requiere la autorización del Administrador del comercio.';
        }

        if ($errores !== []) {
            throw ErrorInventarioException::datosInvalidos($errores);
        }

        return new self(
            $solicitud['id_comercio'],
            $solicitud['id_variacion'],
            $solicitud['id_ubicacion_origen'],
            $solicitud['id_ubicacion_destino'],
            $solicitud['cantidad'],
            $justificacion,
            $autorizacion,
        );
    }
}
