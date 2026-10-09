<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class M5DependenciesMockTest extends TestCase
{
    public function test_m3_catalogo_mock()
    {
        Http::fake([
            'api/m3/v1/variaciones' => Http::response([
                'id_variacion' => 1,
                "nombre" => "Producto de prueba",
                "precio" => 15000,
                'tarifa_impuesto' => 13.00,
                'id_comercio' => 2
            ], 200)
        ]);

        $response = Http::get('api/m3/v1/variaciones');
        $this->assertEquals(200,$response->status());
        $this->assertEquals('1',$response['id_variacion']);
    }

    public function test_m4_inventario_mock()
    {
        Http::fake([
            'api/m4/v1/reservas' => Http::response([
                'id_reserva' => '3f2b8a52-6c1e-4d0b-89a7-bbc5d8d53f99',
                'expiracion' => now()->addMinutes(15)->toDateTimeString()
            ], 201)
        ]);

        $response = Http::post('api/m4/v1/reservas', [
            'id_variacion' => 1,
            "cantidad" => 2
        ]);
        $this->assertEquals(201,$response->status());
    }

    public function test_m6_pagos_mock()
    {
        Http::fake([
            'api/m6/v1/pagos/confirmacion' => Http::response([
                'status' => 'pago confirmado',
                'transaccion_id' => 'TXN-12345'
            ], 200)
        ]);

        $response = Http::post('api/m6/v1/pagos/confirmacion', [
            'id_pedido' => 1
        ]);
        $this->assertEquals(200,$response->status());
        $this->assertEquals('pago confirmado',$response['status']);
    }
}
