<?php

namespace Tests\Feature\M1_Portal;

use Tests\TestCase;

/**
 * Base de las pruebas de M1. Antes de cada prueba deja los cuatro proveedores
 * (M2, M3, M4 y M5) en modo 'doble', sin importar lo que diga el .env de quien
 * las ejecuta (M1_M2_MODO, M1_M3_MODO, M1_M4_MODO, M1_M5_MODO). Así las
 * pruebas no dependen de que el módulo real tenga datos o esté disponible.
 *
 * Una prueba que necesite el servicio real lo pide de forma explícita con
 * config(['integraciones.m1.<proveedor>.modo' => 'real']).
 */
abstract class PruebaM1 extends TestCase
{
    private const PROVEEDORES = ['m2', 'm3', 'm4', 'm5'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::PROVEEDORES as $proveedor) {
            config(["integraciones.m1.{$proveedor}.modo" => 'doble']);
        }
    }
}
