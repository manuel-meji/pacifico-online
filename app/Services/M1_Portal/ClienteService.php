<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\ClienteNoEncontradoException;
use App\Contracts\M1_Portal\Exceptions\CorreoYaRegistradoException;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\DireccionNoEncontradaException;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M1_Portal\Cliente;
use App\Models\M1_Portal\DireccionCliente;
use App\Models\Nucleo\Usuario;
use App\Services\M1_Portal\DTO\ClienteDTO;
use App\Services\M1_Portal\DTO\DireccionClienteDTO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Clientes y direcciones de envío (CU-010, RF-010; tareas 15 a 18 del plan
 * del PC4). M1 es el dueño de las tablas clientes y direcciones_cliente.
 *
 * Lo usa PortalPublicoService, que ya validó el formato y los largos de los
 * datos. Este servicio aplica las reglas que dependen de lo guardado:
 * - Correo único sin distinguir mayúsculas (RN-M1-14) → ERR-M1-001.
 * - Cédula única → ERR-005 con detalle en 'cedula'.
 * - Alias de dirección único por cliente → ERR-005 con detalle en 'alias_direccion'.
 * - Una sola dirección principal por cliente.
 * - Solo M1 crea y modifica clientes (RN-M1-15). La cuenta de acceso la crea el
 *   núcleo (ServicioIdentidadInterface::crearCuenta); M1 no escribe en usuarios.
 *
 * Las mismas restricciones existen en PostgreSQL. Si dos solicitudes chocan
 * entre la revisión y la escritura, la restricción de la base decide y su
 * error se traduce al mismo código del contrato.
 *
 * La autorización por persona (RN-M1-16) no va aquí: se aplica en la capa web,
 * porque M5, M6 y el núcleo llaman sin sesión de usuario.
 */
class ClienteService
{
    /**
     * Largo de usuarios.nombre en el núcleo.
     */
    private const LARGO_NOMBRE_CUENTA = 150;

    /**
     * Mensaje mientras el núcleo no ofrezca actualizarCorreo.
     */
    public const CORREO_SIN_CAMBIO = 'Por ahora no se puede cambiar el correo electrónico.';

    public function __construct(private ServicioIdentidadInterface $identidad) {}

    // ---------------------------------------------------------------
    // Consultas (M5, M6 y núcleo)
    // ---------------------------------------------------------------

    /**
     * @throws ClienteNoEncontradoException
     */
    public function obtener(int $idCliente): ClienteDTO
    {
        return $this->buscarCliente($idCliente)->aDTO();
    }

    /**
     * @throws ClienteNoEncontradoException si la cuenta no tiene un cliente asociado.
     */
    public function obtenerIdPorUsuario(int $idUsuario): int
    {
        $idCliente = Cliente::query()->where('id_usuario', $idUsuario)->value('id_cliente');

        if ($idCliente === null) {
            throw new ClienteNoEncontradoException('La cuenta no tiene un cliente asociado.');
        }

        return (int) $idCliente;
    }

    /**
     * Direcciones del cliente: la principal primero, después en el orden en
     * que se registraron.
     *
     * @return list<DireccionClienteDTO>
     *
     * @throws ClienteNoEncontradoException
     */
    public function listarDirecciones(int $idCliente): array
    {
        $this->buscarCliente($idCliente);

        return DireccionCliente::query()
            ->where('id_cliente', $idCliente)
            ->orderByDesc('es_principal')
            ->orderBy('id_direccion_cliente')
            ->get()
            ->map(fn (DireccionCliente $direccion): DireccionClienteDTO => $direccion->aDTO())
            ->values()
            ->all();
    }

    /**
     * @throws DireccionNoEncontradaException si no existe o es de otro cliente.
     */
    public function obtenerDireccion(int $idCliente, int $idDireccionCliente): DireccionClienteDTO
    {
        return $this->buscarDireccion($idCliente, $idDireccionCliente)->aDTO();
    }

    // ---------------------------------------------------------------
    // Registro y actualización del cliente
    // ---------------------------------------------------------------

    /**
     * Crea la cuenta en el núcleo, el cliente y su dirección principal en una
     * sola transacción: si un paso falla, no queda ninguno de los tres.
     *
     * @return int id_cliente creado.
     *
     * @throws CorreoYaRegistradoException ERR-M1-001.
     * @throws DatosInvalidosException ERR-005.
     */
    public function registrar(ClienteDTO $cliente, DireccionClienteDTO $direccion, string $contrasena): int
    {
        $this->exigirCorreoLibre($cliente->correoElectronico);
        $this->exigirCedulaLibre($cliente->cedula);

        try {
            return DB::transaction(function () use ($cliente, $direccion, $contrasena): int {
                $usuario = $this->crearCuenta($cliente, $contrasena);

                $nuevo = Cliente::create([
                    'id_usuario' => $usuario->id_usuario,
                    'cedula' => $cliente->cedula,
                    'nombre' => $cliente->nombre,
                    'apellidos' => $cliente->apellidos,
                    'correo_electronico' => $cliente->correoElectronico,
                    'telefono' => $cliente->telefono,
                    'estado_cliente' => Cliente::ESTADO_ACTIVO,
                ]);

                DireccionCliente::create([
                    ...$this->camposDireccion($direccion),
                    'id_cliente' => $nuevo->id_cliente,
                    'es_principal' => true,
                ]);

                return $nuevo->id_cliente;
            });
        } catch (UniqueConstraintViolationException $excepcion) {
            throw $this->traducirDuplicado($excepcion);
        }
    }

    /**
     * Cambia nombre, apellidos y teléfono. No cambia la cédula ni las
     * direcciones (contrato 1.2.0).
     *
     * El correo todavía no se puede cambiar: el inicio de sesión usa
     * usuarios.correo, que solo el núcleo modifica, y el núcleo aún no ofrece
     * actualizarCorreo (solicitado por M1). Se acepta el mismo correo con otras
     * mayúsculas. Cuando el núcleo entregue el método, se quita la revisión de
     * CORREO_SIN_CAMBIO y se llama al núcleo en la misma transacción.
     *
     * @return int id_cliente actualizado.
     *
     * @throws ClienteNoEncontradoException
     * @throws CorreoYaRegistradoException ERR-M1-001, si el correo es de otro cliente.
     * @throws DatosInvalidosException ERR-005, si intenta cambiar el correo.
     */
    public function actualizar(int $idCliente, ClienteDTO $cliente): int
    {
        $actual = $this->buscarCliente($idCliente);
        $this->exigirCorreoLibre($cliente->correoElectronico, exceptoCliente: $idCliente);

        if (mb_strtolower($cliente->correoElectronico) !== mb_strtolower($actual->correo_electronico)) {
            throw new DatosInvalidosException(['correo_electronico' => self::CORREO_SIN_CAMBIO]);
        }

        try {
            $actual->update([
                'nombre' => $cliente->nombre,
                'apellidos' => $cliente->apellidos,
                'correo_electronico' => $cliente->correoElectronico,
                'telefono' => $cliente->telefono,
            ]);
        } catch (UniqueConstraintViolationException $excepcion) {
            throw $this->traducirDuplicado($excepcion);
        }

        return $idCliente;
    }

    // ---------------------------------------------------------------
    // Direcciones
    // ---------------------------------------------------------------

    /**
     * Si la nueva dirección se marca como principal, la anterior deja de serlo.
     *
     * @throws ClienteNoEncontradoException
     * @throws DatosInvalidosException alias repetido para el mismo cliente.
     */
    public function agregarDireccion(int $idCliente, DireccionClienteDTO $direccion): DireccionClienteDTO
    {
        $this->buscarCliente($idCliente);
        $this->exigirAliasLibre($idCliente, $direccion->aliasDireccion);

        try {
            return DB::transaction(function () use ($idCliente, $direccion): DireccionClienteDTO {
                if ($direccion->esPrincipal) {
                    $this->quitarPrincipal($idCliente);
                }

                return DireccionCliente::create([
                    ...$this->camposDireccion($direccion),
                    'id_cliente' => $idCliente,
                    'es_principal' => $direccion->esPrincipal,
                ])->aDTO();
            });
        } catch (UniqueConstraintViolationException $excepcion) {
            throw $this->traducirDuplicado($excepcion);
        }
    }

    /**
     * Reemplaza los datos de una dirección del cliente. Marcarla como principal
     * desmarca la anterior. La principal no se puede desmarcar directamente:
     * se cambia marcando otra, para que el cliente nunca quede sin dirección
     * de envío (direccion_envio que leen M5 y M6).
     *
     * @throws DireccionNoEncontradaException
     * @throws DatosInvalidosException alias repetido o principal desmarcada.
     */
    public function actualizarDireccion(int $idCliente, int $idDireccionCliente, DireccionClienteDTO $direccion): DireccionClienteDTO
    {
        $actual = $this->buscarDireccion($idCliente, $idDireccionCliente);

        if ($actual->es_principal && ! $direccion->esPrincipal) {
            throw new DatosInvalidosException([
                'es_principal' => 'Es la dirección principal. Para cambiarla, marque otra dirección como principal.',
            ]);
        }

        $this->exigirAliasLibre($idCliente, $direccion->aliasDireccion, exceptoDireccion: $idDireccionCliente);

        try {
            return DB::transaction(function () use ($idCliente, $actual, $direccion): DireccionClienteDTO {
                if ($direccion->esPrincipal && ! $actual->es_principal) {
                    $this->quitarPrincipal($idCliente);
                }

                $actual->update([
                    ...$this->camposDireccion($direccion),
                    'es_principal' => $direccion->esPrincipal,
                ]);

                return $actual->refresh()->aDTO();
            });
        } catch (UniqueConstraintViolationException $excepcion) {
            throw $this->traducirDuplicado($excepcion);
        }
    }

    // ---------------------------------------------------------------
    // Apoyo
    // ---------------------------------------------------------------

    private function buscarCliente(int $idCliente): Cliente
    {
        $cliente = Cliente::query()->with('direcciones')->find($idCliente);

        if ($cliente === null) {
            throw new ClienteNoEncontradoException;
        }

        return $cliente;
    }

    private function buscarDireccion(int $idCliente, int $idDireccionCliente): DireccionCliente
    {
        $direccion = DireccionCliente::query()
            ->where('id_cliente', $idCliente)
            ->find($idDireccionCliente);

        if ($direccion === null) {
            throw new DireccionNoEncontradaException;
        }

        return $direccion;
    }

    /**
     * Pide al núcleo la cuenta de acceso con rol Cliente. El correo repetido
     * en usuarios se informa igual que en clientes (ERR-M1-001); otros errores
     * de validación del núcleo llegan como ERR-005.
     */
    private function crearCuenta(ClienteDTO $cliente, string $contrasena): Usuario
    {
        try {
            return $this->identidad->crearCuenta([
                'nombre' => mb_substr(trim("{$cliente->nombre} {$cliente->apellidos}"), 0, self::LARGO_NOMBRE_CUENTA),
                'correo' => $cliente->correoElectronico,
                'contrasena' => $contrasena,
            ], RolUsuario::Cliente);
        } catch (ErrorNucleoException $excepcion) {
            $detalles = $excepcion->detalles ?? [];

            if (isset($detalles['correo'])) {
                throw new CorreoYaRegistradoException(anterior: $excepcion);
            }

            throw new DatosInvalidosException($this->aplanarDetalles($detalles), anterior: $excepcion);
        }
    }

    private function exigirCorreoLibre(string $correo, ?int $exceptoCliente = null): void
    {
        $existe = Cliente::query()
            ->whereRaw('LOWER(correo_electronico) = ?', [mb_strtolower($correo)])
            ->when($exceptoCliente !== null, fn (Builder $consulta) => $consulta->where('id_cliente', '<>', $exceptoCliente))
            ->exists();

        if ($existe) {
            throw new CorreoYaRegistradoException;
        }
    }

    private function exigirCedulaLibre(string $cedula): void
    {
        if (Cliente::query()->where('cedula', $cedula)->exists()) {
            throw new DatosInvalidosException(['cedula' => 'Ya hay un cliente registrado con esa cédula.']);
        }
    }

    private function exigirAliasLibre(int $idCliente, string $alias, ?int $exceptoDireccion = null): void
    {
        $existe = DireccionCliente::query()
            ->where('id_cliente', $idCliente)
            ->where('alias_direccion', $alias)
            ->when($exceptoDireccion !== null, fn (Builder $consulta) => $consulta->where('id_direccion_cliente', '<>', $exceptoDireccion))
            ->exists();

        if ($existe) {
            throw new DatosInvalidosException(['alias_direccion' => 'Ya tiene una dirección con ese alias.']);
        }
    }

    private function quitarPrincipal(int $idCliente): void
    {
        DireccionCliente::query()
            ->where('id_cliente', $idCliente)
            ->where('es_principal', true)
            ->update(['es_principal' => false]);
    }

    /**
     * @return array<string, string>
     */
    private function camposDireccion(DireccionClienteDTO $direccion): array
    {
        return [
            'alias_direccion' => $direccion->aliasDireccion,
            'nombre_destinatario' => $direccion->nombreDestinatario,
            'telefono_destinatario' => $direccion->telefonoDestinatario,
            'provincia' => $direccion->provincia,
            'canton' => $direccion->canton,
            'distrito' => $direccion->distrito,
            'otras_senas' => $direccion->otrasSenas,
        ];
    }

    /**
     * Choque con una restricción de unicidad de PostgreSQL entre la revisión y
     * la escritura (dos solicitudes simultáneas). Se identifica por el nombre
     * de la restricción o de la columna en el mensaje de la base.
     */
    private function traducirDuplicado(UniqueConstraintViolationException $excepcion): DatosInvalidosException|CorreoYaRegistradoException
    {
        $mensaje = $excepcion->getMessage();

        return match (true) {
            str_contains($mensaje, 'correo') => new CorreoYaRegistradoException(anterior: $excepcion),
            str_contains($mensaje, 'cedula') => new DatosInvalidosException(['cedula' => 'Ya hay un cliente registrado con esa cédula.'], anterior: $excepcion),
            str_contains($mensaje, 'alias') => new DatosInvalidosException(['alias_direccion' => 'Ya tiene una dirección con ese alias.'], anterior: $excepcion),
            str_contains($mensaje, 'principal') => new DatosInvalidosException(['es_principal' => 'El cliente ya tiene una dirección principal.'], anterior: $excepcion),
            default => new DatosInvalidosException([], 'Los datos chocan con un registro existente.', $excepcion),
        };
    }

    /**
     * Detalles del núcleo (campo → lista de mensajes) al formato de M1
     * (campo → mensaje).
     *
     * @param  array<string, mixed>  $detalles
     * @return array<string, string>
     */
    private function aplanarDetalles(array $detalles): array
    {
        return array_map(
            fn (mixed $mensajes): string => is_array($mensajes) ? (string) ($mensajes[0] ?? '') : (string) $mensajes,
            $detalles,
        );
    }
}
