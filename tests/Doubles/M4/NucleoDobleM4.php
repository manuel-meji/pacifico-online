<?php

namespace Tests\Doubles\M4;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use InvalidArgumentException;

/**
 * Doble de prueba del núcleo para M4: comercio de la sesión configurable (RN-12) y bitácora en
 * memoria que además sirve de espía. Implementa NucleoDependencia y se conecta en
 * config/integraciones.php (clave m4.nucleo.doble).
 *
 * Cada registro lleva los campos de la bitácora del Comité (id_usuario, fecha_hora, accion,
 * modulo, entidad, entidad_id). Solo admite inserciones y rechaza acción o entidad vacías o
 * más largas de lo permitido.
 */
class NucleoDobleM4 implements NucleoDependencia
{
    /** @var list<array{id_usuario: int, fecha_hora: string, accion: string, modulo: string, entidad: string, entidad_id: int, detalles: array<string, mixed>}> */
    private array $bitacora = [];

    public function __construct(
        private ?int $idComercio = 104,
        private int $idUsuario = 1,
    ) {}

    /** Cambia la sesión simulada; null representa una petición sin ámbito de comercio. */
    public function actuarComo(?int $idComercio, int $idUsuario = 1): void
    {
        $this->idComercio = $idComercio;
        $this->idUsuario = $idUsuario;
    }

    public function comercioActual(): ?int
    {
        return $this->idComercio;
    }

    public function registrarAuditoria(string $accion, string $entidad, int $entidadId, array $detalles = []): void
    {
        foreach (['accion' => [$accion, 100], 'entidad' => [$entidad, 60]] as $campo => [$valor, $maximo]) {
            if (trim($valor) === '' || mb_strlen($valor) > $maximo) {
                throw new InvalidArgumentException("{$campo} es obligatorio y admite hasta {$maximo} caracteres.");
            }
        }

        $this->bitacora[] = [
            'id_usuario' => $this->idUsuario,
            'fecha_hora' => date(DATE_ATOM),
            'accion' => $accion,
            'modulo' => 'M4',
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'detalles' => $detalles,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function bitacora(): array
    {
        return $this->bitacora;
    }

    public function fueRegistrada(string $accion, string $entidad): bool
    {
        foreach ($this->bitacora as $registro) {
            if ($registro['accion'] === $accion && $registro['entidad'] === $entidad) {
                return true;
            }
        }

        return false;
    }
}
