<?php

namespace Database\Seeders\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\CorreoYaRegistradoException;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Models\M1_Portal\Cliente;
use App\Services\M1_Portal\ClienteService;
use App\Services\M1_Portal\DTO\ClienteDTO;
use App\Services\M1_Portal\DTO\DireccionClienteDTO;
use Database\Seeders\Nucleo\ContrasenaDemo;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Cliente del escenario 1 de la sección 2.2 del colectivo del PC4: Jorge Araya, con su
 * dirección principal en Liberia. Es quien compra durante el recorrido de demostración.
 *
 * Se registra con ClienteService::registrar, igual que desde la pantalla de registro: el
 * núcleo crea la cuenta con rol Cliente (ServicioIdentidad::crearCuenta) y M1 guarda el
 * cliente y la dirección, todo en una transacción. Este seeder solo escribe en tablas de
 * M1; no toca usuarios. No necesita sesión: se puede ejecutar desde la consola.
 *
 * La contraseña es la de DEMO_CONTRASENA del .env, la misma de CuentasDemoSeeder. Si falta
 * o es más corta que el mínimo del núcleo, se detiene sin crear nada (ver ContrasenaDemo).
 *
 * Es idempotente: si ya hay un cliente con ese correo, no hace nada y lo informa (tampoco
 * le cambia la contraseña). Si la cuenta existe en el núcleo pero el cliente no, falla con
 * un mensaje que lo explica, porque M1 no puede adoptar ni borrar cuentas del núcleo.
 *     php artisan db:seed --class="Database\Seeders\M1_Portal\ClientesDemoSeeder"
 */
class ClientesDemoSeeder extends Seeder
{
    public const NOMBRE = 'Jorge';

    public const APELLIDOS = 'Araya';

    public const CORREO = 'jorge.araya.demopacificos@gmail.com';

    public const CEDULA = '1-1111-1111';

    public const TELEFONO = '8888-1111';

    /** @var array{alias_direccion: string, nombre_destinatario: string, telefono_destinatario: string, provincia: string, canton: string, distrito: string, otras_senas: string} */
    public const DIRECCION = [
        'alias_direccion' => 'Casa',
        'nombre_destinatario' => 'Jorge Araya',
        'telefono_destinatario' => '8888-1111',
        'provincia' => 'Guanacaste',
        'canton' => 'Liberia',
        'distrito' => 'Liberia',
        'otras_senas' => 'Barrio Capulín',
    ];

    public function run(ClienteService $clientes): void
    {
        // La validación va antes de cualquier consulta o escritura.
        $contrasena = ContrasenaDemo::exigir('ClientesDemoSeeder');

        if ($this->yaExiste()) {
            $this->command?->info('M1: el cliente '.self::CORREO.' ya existe; no se cambió nada.');

            return;
        }

        try {
            $clientes->registrar($this->cliente(), $this->direccion(), $contrasena);
        } catch (CorreoYaRegistradoException $excepcion) {
            throw new RuntimeException(
                'ClientesDemoSeeder: no se creó el cliente. El correo '.self::CORREO.' ya tiene una cuenta en el núcleo '
                .'(usuarios), pero no hay un cliente de M1 con ese correo. M1 no puede modificar esa cuenta: '
                .'pida al núcleo que la elimine y vuelva a ejecutar el seeder.',
                previous: $excepcion,
            );
        } catch (DatosInvalidosException $excepcion) {
            throw new RuntimeException(
                'ClientesDemoSeeder: no se creó el cliente. Datos rechazados: '.$this->enTexto($excepcion->detalles()),
                previous: $excepcion,
            );
        }

        $this->command?->info('M1: cliente '.self::CORREO.' del escenario de demostración listo.');
    }

    /**
     * Misma comparación que ClienteService: el correo no distingue mayúsculas (RN-M1-14).
     */
    private function yaExiste(): bool
    {
        return Cliente::query()
            ->whereRaw('LOWER(correo_electronico) = ?', [mb_strtolower(self::CORREO)])
            ->exists();
    }

    private function cliente(): ClienteDTO
    {
        return new ClienteDTO(
            idCliente: null,
            nombre: self::NOMBRE,
            apellidos: self::APELLIDOS,
            correoElectronico: self::CORREO,
            telefono: self::TELEFONO,
            cedula: self::CEDULA,
            direccionEnvio: '',
        );
    }

    private function direccion(): DireccionClienteDTO
    {
        return new DireccionClienteDTO(
            idDireccionCliente: null,
            idCliente: null,
            aliasDireccion: self::DIRECCION['alias_direccion'],
            nombreDestinatario: self::DIRECCION['nombre_destinatario'],
            telefonoDestinatario: self::DIRECCION['telefono_destinatario'],
            provincia: self::DIRECCION['provincia'],
            canton: self::DIRECCION['canton'],
            distrito: self::DIRECCION['distrito'],
            otrasSenas: self::DIRECCION['otras_senas'],
            esPrincipal: true,
        );
    }

    /**
     * @param  array<string, string>  $detalles  Campo → mensaje.
     */
    private function enTexto(array $detalles): string
    {
        if ($detalles === []) {
            return 'sin detalle.';
        }

        return implode(' ', array_map(
            fn (string $campo, string $mensaje): string => "{$campo}: {$mensaje}",
            array_keys($detalles),
            $detalles,
        ));
    }
}
