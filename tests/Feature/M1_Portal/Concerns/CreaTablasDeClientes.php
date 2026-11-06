<?php

namespace Tests\Feature\M1_Portal\Concerns;

use App\Contracts\Nucleo\ServicioIdentidadInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Doubles\M1\IdentidadDoble;

/**
 * Prepara las pruebas de clientes de M1 en el SQLite en memoria del proyecto.
 *
 * Las migraciones de M1 usan SQL de PostgreSQL y no corren en SQLite, así que
 * se crea una versión simple de clientes y direcciones_cliente, con las mismas
 * columnas y sin las restricciones propias de PostgreSQL: se prueba la lógica
 * de M1, no la base. Esas restricciones se validan en vivo contra la base del
 * ambiente.
 *
 * El núcleo se reemplaza por IdentidadDoble.
 */
trait CreaTablasDeClientes
{
    protected IdentidadDoble $identidad;

    protected function prepararClientes(): void
    {
        Schema::create('clientes', function (Blueprint $tabla) {
            $tabla->id('id_cliente');
            $tabla->unsignedBigInteger('id_usuario')->unique();
            $tabla->string('cedula', 20)->unique();
            $tabla->string('nombre', 80);
            $tabla->string('apellidos', 120);
            $tabla->string('correo_electronico', 150);
            $tabla->string('telefono', 20);
            $tabla->string('estado_cliente', 20)->default('activo');
            $tabla->timestamps();
        });

        Schema::create('direcciones_cliente', function (Blueprint $tabla) {
            $tabla->id('id_direccion_cliente');
            $tabla->unsignedBigInteger('id_cliente');
            $tabla->string('alias_direccion', 40);
            $tabla->string('nombre_destinatario', 150);
            $tabla->string('telefono_destinatario', 20);
            $tabla->string('provincia', 40);
            $tabla->string('canton', 60);
            $tabla->string('distrito', 60);
            $tabla->string('otras_senas', 300);
            $tabla->boolean('es_principal')->default(false);
            $tabla->timestamps();
            $tabla->unique(['id_cliente', 'alias_direccion']);
        });

        $this->identidad = new IdentidadDoble;
        $this->app->instance(ServicioIdentidadInterface::class, $this->identidad);
    }
}
