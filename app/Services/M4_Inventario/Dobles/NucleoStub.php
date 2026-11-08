<?php

namespace App\Services\M4_Inventario\Dobles;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Services\Nucleo\ContextoComercio;
use Closure;

/**
 * Doble del núcleo para M4: simula la sesión de un comercio (por defecto el 1) y
 * guarda la bitácora en memoria para que una prueba pueda revisar qué se registró.
 *
 * Simula la sesión estableciendo ese comercio en ContextoComercio, así el Global
 * Scope del núcleo filtra igual que en producción. Con comercio null corre en modo
 * sistema. Se sustituye en config/integraciones.php, clave m4.nucleo.doble.
 */
class NucleoStub implements NucleoDependencia
{
    /** @var list<array{accion: string, entidad: string, entidad_id: int, detalles: array<string, mixed>}> */
    public array $bitacora = [];

    public function __construct(private ?int $comercio = 1, private int $usuario = 1) {}

    public function comercioActual(): ?int
    {
        return $this->comercio;
    }

    public function usuarioActual(): int
    {
        return $this->usuario;
    }

    public function ejecutarEnAmbito(Closure $operacion, bool $publica = false): mixed
    {
        $contexto = app(ContextoComercio::class);

        if ($this->comercio === null || $publica) {
            return $contexto->ejecutarComoSistema($operacion);
        }

        $contexto->establecer($this->comercio);

        return $operacion();
    }

    public function registrarAuditoria(string $accion, string $entidad, int $entidadId, array $detalles = []): void
    {
        $this->bitacora[] = ['accion' => $accion, 'entidad' => $entidad, 'entidad_id' => $entidadId, 'detalles' => $detalles];
    }
}
