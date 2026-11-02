<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Nucleo\Rol;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CrearEncargadoPlataforma extends Command
{
    protected $signature = 'nucleo:crear-encargado-plataforma';
    protected $description = 'Crea el primer Encargado de la plataforma desde variables de entorno';

    public function handle(): int
    {
        $existe = DB::table('usuarios')
            ->where('rol', Rol::EncargadoPlataforma->value)
            ->where('activo', true)
            ->exists();

        if ($existe) {
            $this->warn('Ya existe un Encargado de la plataforma activo. No se crea otro.');
            return self::FAILURE;
        }

        $datos = [
            'nombre'     => env('NUCLEO_ENCARGADO_NOMBRE'),
            'correo'     => env('NUCLEO_ENCARGADO_CORREO'),
            'contrasena' => env('NUCLEO_ENCARGADO_CONTRASENA'),
        ];

        $validador = Validator::make($datos, [
            'nombre'     => 'required|string|max:255',
            'correo'     => 'required|email|unique:usuarios,correo',
            'contrasena' => 'required|string|min:8',
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $mensaje) {
                $this->error($mensaje);
            }
            return self::FAILURE;
        }

        // TODO: reemplazar por ServicioIdentidad::crearCuenta($datos, Rol::EncargadoPlataforma, null) (Persona 4)
        // TODO: registrar la creación en BitacoraAuditoria (Persona 7)
        DB::table('usuarios')->insert([
            'nombre'      => $datos['nombre'],
            'correo'      => $datos['correo'],
            'contrasena'  => Hash::make($datos['contrasena']),
            'rol'         => Rol::EncargadoPlataforma->value,
            'id_comercio' => null,
            'activo'      => true,
        ]);

        $this->info('Encargado de la plataforma creado.');
        return self::SUCCESS;
    }
}