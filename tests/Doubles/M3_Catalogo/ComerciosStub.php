<?php

namespace Tests\Doubles\M3_Catalogo;

use App\Services\M3_Catalogo\Dobles\ComerciosStub as ComerciosStubM3;

/**
 * Doble de M2 para las pruebas de M3: el mismo que usa la aplicación en modo
 * doble. Implementa obtenerComercioActivo con la forma del contrato v1.2.1
 * (id_comercio, estado, activo, motivo_estado). Comercios de prueba: 1, 34, 35
 * y 36 activos; 37 suspendido; cualquier otro no existe (ERR-004).
 */
class ComerciosStub extends ComerciosStubM3
{
}