<?php

namespace Tests\Feature\M1_Portal\Concerns;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Models\Nucleo\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara las pruebas de destacados pagados de M1 (contrato 1.4.0, SC-08) en
 * el SQLite en memoria del proyecto.
 *
 * Las migraciones de M1 usan SQL de PostgreSQL (CHECK, índice único parcial y
 * llaves foráneas hacia M2, M3 y el núcleo) que no corre en SQLite, así que se
 * crea una versión simple de las tablas con las mismas columnas. Se prueba la
 * lógica de M1; las restricciones de la base se validan contra el ambiente.
 *
 * La bitácora de auditoría del núcleo (que escribe EstablecerComercio cuando
 * entra el Encargado de la plataforma) se reemplaza por un doble.
 */
trait CreaTablasDeDestacados
{
    protected function prepararDestacados(): void
    {
        Schema::create('productos_destacados', function (Blueprint $tabla): void {
            $tabla->id('id_producto_destacado');
            $tabla->unsignedBigInteger('id_producto');
            $tabla->smallInteger('posicion');
            $tabla->timestamp('fecha_inicio');
            $tabla->timestamp('fecha_fin');
            $tabla->boolean('activo')->default(true);
            $tabla->unsignedBigInteger('id_comercio')->nullable();
            $tabla->string('origen', 20)->default('plataforma');
            $tabla->unsignedBigInteger('id_solicitud_destacado')->nullable()->unique();
            $tabla->timestamps();
        });

        Schema::create('comercios_destacados', function (Blueprint $tabla): void {
            $tabla->id('id_comercio_destacado');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->smallInteger('posicion');
            $tabla->timestamp('fecha_inicio');
            $tabla->timestamp('fecha_fin');
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });

        Schema::create('tarifas_destacado', function (Blueprint $tabla): void {
            $tabla->id('id_tarifa_destacado');
            $tabla->smallInteger('periodo_dias')->unique();
            $tabla->decimal('monto', 12, 2);
            $tabla->char('moneda', 3)->default('CRC');
            $tabla->unsignedBigInteger('id_usuario_actualiza')->nullable();
            $tabla->timestamps();
        });

        Schema::create('solicitudes_destacado', function (Blueprint $tabla): void {
            $tabla->id('id_solicitud_destacado');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->unsignedBigInteger('id_producto');
            $tabla->smallInteger('periodo_dias');
            $tabla->decimal('monto', 12, 2);
            $tabla->char('moneda', 3)->default('CRC');
            $tabla->string('estado', 20)->default('pendiente');
            $tabla->string('motivo_rechazo', 500)->nullable();
            $tabla->unsignedBigInteger('id_usuario_solicita');
            $tabla->unsignedBigInteger('id_usuario_resuelve')->nullable();
            $tabla->timestamp('fecha_resolucion')->nullable();
            $tabla->timestamps();
        });

        Schema::create('cargos_destacado', function (Blueprint $tabla): void {
            $tabla->id('id_cargo_destacado');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->unsignedBigInteger('id_solicitud_destacado')->unique();
            $tabla->unsignedBigInteger('id_producto_destacado');
            $tabla->unsignedBigInteger('id_producto');
            $tabla->smallInteger('periodo_dias');
            $tabla->decimal('monto', 12, 2);
            $tabla->char('moneda', 3)->default('CRC');
            $tabla->timestamp('fecha_cargo');
            $tabla->timestamp('fecha_inicio');
            $tabla->timestamp('fecha_fin');
            $tabla->timestamps();
        });

        $this->mock(NucleoServiceInterface::class)->shouldReceive('registrarAuditoria')->zeroOrMoreTimes();
    }

    /**
     * Cuenta del núcleo en memoria con su rol (la sesión se simula con actingAs).
     */
    protected function cuentaConRol(int $idUsuario, RolUsuario $rol, ?int $idComercio = null): Usuario
    {
        $usuario = new Usuario;
        $usuario->forceFill([
            'id_usuario' => $idUsuario,
            'rol' => $rol,
            'id_comercio' => $idComercio,
            'activo' => true,
        ]);

        return $usuario;
    }
}
