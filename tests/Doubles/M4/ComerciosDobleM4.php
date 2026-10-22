<?php

namespace Tests\Doubles\M4;

use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Doble de prueba de M2 para M4 (contrato openapi_M2 v1.2.1: ObtenerComercioActivo y
 * ObtenerSedesComercio). Implementa ComerciosDependencia, así que se conecta cambiando la
 * clase en config/integraciones.php (clave m4.m2.doble), sin tocar el código de M4.
 *
 * Cubre los cuatro estados del comercio, sedes habilitadas y deshabilitadas, comercio sin
 * sedes, comercio inexistente (M4 lo trata como ERR-003, RN-12) e identificador inválido (ERR-005).
 * La variante ComerciosNoDisponible simula que M2 no responde (ERR-006).
 */
class ComerciosDobleM4 implements ComerciosDependencia
{
    public function obtenerComercioActivo(int $idComercio): array
    {
        $comercio = $this->comercio($idComercio);

        return $comercio + ['activo' => $comercio['estado'] === 'Activo'];
    }

    /**
     * Devuelve todas las sedes con su bandera habilitada; M4 descarta las deshabilitadas.
     * id_sede de M2 es el mismo identificador que id_ubicacion de M4.
     */
    public function obtenerSedesComercio(int $idComercio): array
    {
        $this->comercio($idComercio);

        return ['id_comercio' => $idComercio, 'sedes' => DatosPruebaM4::sedes()[$idComercio] ?? []];
    }

    /** @return array{id_comercio: int, estado: string, motivo_estado: string|null} */
    private function comercio(int $idComercio): array
    {
        if ($idComercio < 1) {
            throw ErrorInventarioException::datosInvalidos(['id_comercio' => 'Debe ser un entero mayor o igual a 1.']);
        }

        return DatosPruebaM4::comercios()[$idComercio] ?? throw ErrorInventarioException::recursoNoAccesible();
    }
}
