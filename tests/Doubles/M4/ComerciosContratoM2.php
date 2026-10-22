<?php

namespace Tests\Doubles\M4;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use LogicException;

/**
 * Doble que imita a M2 tal como lo define su contrato (openapi_M2 v1.2.1), para probar el
 * adaptador real de M4 (ComerciosReal) sin esperar a M2. Implementa ComerciosServiceInterface y
 * agrega las dos operaciones que M4 consume, con las firmas que M4 propone a M2 (sección 1.1.1):
 * obtenerComercioActivo (GET /comercios/{id_comercio}/estado) y obtenerSedesComercio
 * (GET /comercios/{id_comercio}/sedes). Errores en el formato del núcleo: ERR-004 y ERR-005.
 */
class ComerciosContratoM2 implements ComerciosServiceInterface
{
    /** @return array{id_comercio: int, estado: string, activo: bool, motivo_estado: string|null} */
    public function obtenerComercioActivo(int $idComercio): array
    {
        $comercio = $this->comercio($idComercio);

        return $comercio + ['activo' => $comercio['estado'] === 'Activo'];
    }

    /** @return array{id_comercio: int, sedes: list<array{id_sede: int, nombre: string, direccion: string, habilitada: bool}>} */
    public function obtenerSedesComercio(int $idComercio): array
    {
        $this->comercio($idComercio);

        return ['id_comercio' => $idComercio, 'sedes' => DatosPruebaM4::sedes()[$idComercio] ?? []];
    }

    public function obtenerMicrositio(int $comercioId): array
    {
        throw new LogicException('Fuera del alcance del doble de M4.');
    }

    public function registrarComision(int $pedidoId, float $subtotalPedido): bool
    {
        throw new LogicException('Fuera del alcance del doble de M4.');
    }

    /** @return array{id_comercio: int, estado: string, motivo_estado: string|null} */
    private function comercio(int $idComercio): array
    {
        if ($idComercio < 1) {
            throw ErrorProveedorException::datosInvalidos(['id_comercio' => 'Debe ser un entero mayor o igual a 1.']);
        }

        return DatosPruebaM4::comercios()[$idComercio] ?? throw ErrorProveedorException::noEncontrado('El comercio no existe.');
    }
}
