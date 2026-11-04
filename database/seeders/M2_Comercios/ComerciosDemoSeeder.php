<?php

declare(strict_types=1);

namespace Database\Seeders\M2_Comercios;

use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\ComisionComercio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Los cinco comercios del escenario de demostración (sección 2.2 del colectivo del PC4):
 * todos Activo, con micrositio y comisión vigente desde el 1/11/2026 (RN-010: sobre
 * el subtotal del subpedido). Las cédulas jurídicas son ficticias.
 *
 * Es idempotente: se puede ejecutar varias veces sin duplicar datos.
 *     php artisan db:seed --class="Database\Seeders\M2_Comercios\ComerciosDemoSeeder"
 */
class ComerciosDemoSeeder extends Seeder
{
    public const VIGENTE_DESDE = '2026-11-01 00:00:00';

    /** @var list<array<string, mixed>> */
    public const COMERCIOS = [
        ['cedula' => '3-101-100001', 'nombre' => 'Soluciones del Milenio', 'razon' => 'Soluciones del Milenio S.A.', 'direccion' => 'Liberia, Guanacaste', 'tipo' => 'porcentaje', 'valor' => 8, 'moneda' => null, 'destacado' => true],
        ['cedula' => '3-101-100002', 'nombre' => 'Temulandia', 'razon' => 'Temulandia Comercial S.A.', 'direccion' => 'Nicoya, Guanacaste', 'tipo' => 'monto_fijo', 'valor' => 1000, 'moneda' => 'CRC', 'destacado' => false],
        ['cedula' => '3-101-100003', 'nombre' => 'Elegancia', 'razon' => 'Elegancia Moda S.A.', 'direccion' => 'Santa Cruz, Guanacaste', 'tipo' => 'porcentaje', 'valor' => 10, 'moneda' => null, 'destacado' => false],
        ['cedula' => '3-101-100004', 'nombre' => 'Deportes Pacífico', 'razon' => 'Deportes Pacífico S.A.', 'direccion' => 'Cañas, Guanacaste', 'tipo' => 'porcentaje', 'valor' => 9, 'moneda' => null, 'destacado' => false],
        ['cedula' => '3-101-100005', 'nombre' => 'Café Volcán', 'razon' => 'Café Volcán S.A.', 'direccion' => 'Tilarán, Guanacaste', 'tipo' => 'porcentaje', 'valor' => 7, 'moneda' => null, 'destacado' => true],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::COMERCIOS as $i => $datos) {
                $this->sembrar($datos, $i + 1);
            }
        });
    }

    /** @param  array<string, mixed>  $d */
    private function sembrar(array $d, int $n): void
    {
        $comercio = Comercio::query()->updateOrCreate(
            ['cedula_juridica' => $d['cedula']],
            [
                'nombre_comercial' => $d['nombre'],
                'razon_social' => $d['razon'],
                'direccion_fiscal' => $d['direccion'],
                'inscrito_como_contribuyente' => true,
                'estado' => Comercio::ACTIVO,
            ],
        );
        $id = ['id_comercio' => $comercio->id_comercio];

        $comercio->micrositio()->updateOrCreate($id, [
            'descripcion' => "{$d['nombre']}: comercio de demostración de Pacíficos Online.",
            'logotipo' => null,
        ]);

        ComisionComercio::query()->updateOrCreate(
            $id + ['vigente_desde' => self::VIGENTE_DESDE],
            ['tipo' => $d['tipo'], 'valor' => $d['valor'], 'moneda' => $d['moneda']],
        );

        foreach (range(1, 7) as $dia) {
            $cerrado = $dia === 7;
            $comercio->horarios()->updateOrCreate($id + ['dia_semana' => $dia], [
                'hora_apertura' => $cerrado ? null : '08:00',
                'hora_cierre' => $cerrado ? null : '18:00',
                'cerrado' => $cerrado,
            ]);
        }

        $comercio->contactos()->updateOrCreate($id + ['tipo' => 'telefono'], ['valor' => sprintf('2666-%04d', $n)]);
        $comercio->contactos()->updateOrCreate($id + ['tipo' => 'correo'], ['valor' => "contacto{$n}@demo.pacificos.test"]);

        $comercio->politicas()->updateOrCreate($id + ['tipo' => 'envio'], [
            'contenido' => 'Envío a todo el país; el costo depende de la zona.',
            'configuracion_envio' => [
                ['zona' => 'GAM', 'costo' => 2500, 'moneda' => 'CRC'],
                ['zona' => 'Resto del país', 'costo' => 3500, 'moneda' => 'CRC'],
            ],
        ]);

        // Publicidad vigente solo para demostrar el filtro de destacados de M1 (pendiente de confirmar con M1).
        if ($d['destacado']) {
            $comercio->publicidades()->updateOrCreate(
                $id + ['ubicacion' => 'portada'],
                ['inicio' => '2026-11-01', 'fin' => '2026-12-31', 'activa' => true],
            );
        }
    }
}
