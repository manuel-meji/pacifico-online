<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Models\Nucleo\Rol;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CrearEncargadoPlataforma extends Command
{
    protected $signature = 'nucleo:crear-encargado-plataforma';
    protected $description = 'Crea el primer Encargado de la plataforma desde variables de entorno';

    
    public function __construct(
    private readonly ServicioIdentidadInterface $servicioIdentidad,
    private readonly NucleoServiceInterface $nucleoService,) {
    parent::__construct();
    }
    public function handle(): int
    {
        $existe = DB::table('usuarios')
            ->where('rol', RolUsuario::EncargadoPlataforma->value)
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

        $usuario = DB::transaction(function () use ($datos) {
    $usuario = $this->servicioIdentidad->crearCuenta(
        $datos,
        RolUsuario::EncargadoPlataforma,
        null
    );

    $this->nucleoService->registrarAuditoria(
        'encargado_plataforma_creado',
        'usuarios',
        (int) $usuario->id_usuario,
        [
            'modulo' => 'NUCLEO',
            'resultado' => 'exitoso',
        ]
    );

    return $usuario;
    });

        $this->info('Encargado de la plataforma creado.');
        return self::SUCCESS;
    }
}