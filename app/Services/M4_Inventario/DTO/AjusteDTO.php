<?php

namespace App\Services\M4_Inventario\DTO;

use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Conteo físico y ajuste (AjusteSolicitud del contrato).
 */
final readonly class AjusteDTO
{
    public const MOTIVOS = ['merma', 'robo', 'danio', 'sobrante', 'error_conteo', 'otro'];

    public function __construct(
        public int $idComercio,
        public int $idVariacion,
        public int $idUbicacion,
        public int $cantidadSistemaVista,
        public int $cantidadContada,
        public string $motivoAjuste,
        public string $justificacion,
        public ?string $idAutorizacion,
    ) {}

    /**
     * @param  array<string, mixed>  $solicitud
     */
    public static function desdeSolicitud(array $solicitud): self
    {
        $errores = [];

        foreach (['id_comercio', 'id_variacion', 'id_ubicacion'] as $campo) {
            if (! is_int($solicitud[$campo] ?? null) || $solicitud[$campo] < 1) {
                $errores[$campo] = 'Debe ser un entero mayor o igual a 1.';
            }
        }

        foreach (['cantidad_sistema_vista', 'cantidad_contada'] as $campo) {
            if (! is_int($solicitud[$campo] ?? null) || $solicitud[$campo] < 0) {
                $errores[$campo] = 'Debe ser un entero mayor o igual a 0.';
            }
        }

        if (! in_array($solicitud['motivo_ajuste'] ?? null, self::MOTIVOS, true)) {
            $errores['motivo_ajuste'] = 'Debe ser uno de: '.implode(', ', self::MOTIVOS).'.';
        }

        $justificacion = trim((string) ($solicitud['justificacion'] ?? ''));

        if ($justificacion === '' || mb_strlen($justificacion) > 255) {
            $errores['justificacion'] = 'Es obligatoria y admite hasta 255 caracteres.';
        }

        $autorizacion = isset($solicitud['id_autorizacion']) ? trim((string) $solicitud['id_autorizacion']) : null;

        if ($errores !== []) {
            throw ErrorInventarioException::datosInvalidos($errores);
        }

        return new self(
            $solicitud['id_comercio'],
            $solicitud['id_variacion'],
            $solicitud['id_ubicacion'],
            $solicitud['cantidad_sistema_vista'],
            $solicitud['cantidad_contada'],
            $solicitud['motivo_ajuste'],
            $justificacion,
            $autorizacion === '' ? null : $autorizacion,
        );
    }
}
