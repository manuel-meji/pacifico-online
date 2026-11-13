<?php

namespace Tests\Doubles\M1;

use App\Contracts\M1_Portal\Exceptions\ClienteNoEncontradoException;
use App\Contracts\M1_Portal\Exceptions\CorreoYaRegistradoException;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\DireccionNoEncontradaException;
use App\Contracts\M1_Portal\PortalPublicoContract;
use LogicException;

/**
 * Doble de PortalPublicoContract (contrato M1 v1.1.0) para los módulos que
 * consumen a M1: M5 (cliente y direcciones), M6 (cliente) y el núcleo
 * (obtenerIdClientePorUsuario). Es el doble_de_prueba que declara el contrato.
 *
 * Guarda los clientes y direcciones en memoria: lo que se registra o actualiza
 * se ve en las consultas mientras viva la instancia. Las operaciones de
 * navegación (búsqueda, ficha, destacados, categorías, comercios y micrositio)
 * solo las usan los controladores de M1 y quedan fuera de este doble.
 *
 * Uso en una prueba de otro módulo:
 *     $this->app->instance(PortalPublicoContract::class, new PortalPublicoFake);
 */
class PortalPublicoFake implements PortalPublicoContract
{
    public const CLIENTE_CON_DIRECCIONES = 1;

    public const CLIENTE_SIN_DIRECCIONES = 77;

    public const CLIENTE_INEXISTENTE = 999;

    public const USUARIO_CON_CLIENTE = 501;

    public const USUARIO_SIN_CLIENTE = 900;

    public const DIRECCION_PRINCIPAL = 11;

    public const DIRECCION_SECUNDARIA = 12;

    public const DIRECCION_DE_OTRO_CLIENTE = 13;

    /**
     * @var array<int, array<string, mixed>> id_cliente → datos con id_usuario.
     */
    private array $clientes;

    /**
     * @var array<int, array<string, mixed>> id_direccion_cliente → esquema DireccionCliente.
     */
    private array $direcciones;

    private int $siguienteIdCliente = 100;

    private int $siguienteIdDireccion = 100;

    public function __construct()
    {
        $this->clientes = [
            self::CLIENTE_CON_DIRECCIONES => [
                'id_cliente' => self::CLIENTE_CON_DIRECCIONES,
                'id_usuario' => self::USUARIO_CON_CLIENTE,
                'nombre' => 'Ana',
                'apellidos' => 'Mora Rojas',
                'correo_electronico' => 'ana.mora@example.com',
                'telefono' => '8888-0001',
                'cedula' => '503450678',
            ],
            self::CLIENTE_SIN_DIRECCIONES => [
                'id_cliente' => self::CLIENTE_SIN_DIRECCIONES,
                'id_usuario' => 502,
                'nombre' => 'Fulano',
                'apellidos' => 'Castillo',
                'correo_electronico' => 'fulano@example.com',
                'telefono' => '8888-0002',
                // La misma cédula que usa el FakeClienteClient de M6 para el cliente 77.
                'cedula' => '116540892',
            ],
            3 => [
                'id_cliente' => 3,
                'id_usuario' => 503,
                'nombre' => 'Luis',
                'apellidos' => 'Vargas Solís',
                'correo_electronico' => 'luis.vargas@example.com',
                'telefono' => '8888-0003',
                'cedula' => '501230456',
            ],
        ];

        $this->direcciones = [
            self::DIRECCION_SECUNDARIA => $this->direccion(self::DIRECCION_SECUNDARIA, self::CLIENTE_CON_DIRECCIONES, 'Trabajo', 'Del hospital 100 m sur', false),
            self::DIRECCION_PRINCIPAL => $this->direccion(self::DIRECCION_PRINCIPAL, self::CLIENTE_CON_DIRECCIONES, 'Casa', '200 m norte del parque', true),
            self::DIRECCION_DE_OTRO_CLIENTE => $this->direccion(self::DIRECCION_DE_OTRO_CLIENTE, 3, 'Casa', 'Barrio La Cruz, casa 4', true),
        ];
    }

    public function obtenerCliente(int $idCliente): array
    {
        $cliente = $this->clientes[$idCliente] ?? throw new ClienteNoEncontradoException;

        $principal = null;

        foreach ($this->direcciones as $direccion) {
            if ($direccion['id_cliente'] === $idCliente && $direccion['es_principal']) {
                $principal = $direccion;
            }
        }

        return [
            'id_cliente' => $cliente['id_cliente'],
            'nombre' => $cliente['nombre'],
            'apellidos' => $cliente['apellidos'],
            'correo_electronico' => $cliente['correo_electronico'],
            'telefono' => $cliente['telefono'],
            'direccion_envio' => $principal === null
                ? ''
                : "{$principal['otras_senas']}, {$principal['distrito']}, {$principal['canton']}, {$principal['provincia']}",
            // Desde 1.7.0 (SC-14): la cédula del cliente, para la factura de M6.
            'identificacion_tributaria' => $cliente['cedula'],
        ];
    }

    public function listarDireccionesCliente(int $idCliente): array
    {
        if (! isset($this->clientes[$idCliente])) {
            throw new ClienteNoEncontradoException;
        }

        $lista = array_values(array_filter(
            $this->direcciones,
            fn (array $direccion): bool => $direccion['id_cliente'] === $idCliente,
        ));

        usort($lista, fn (array $a, array $b): int => [$b['es_principal'], $a['id_direccion_cliente']] <=> [$a['es_principal'], $b['id_direccion_cliente']]);

        return $lista;
    }

    public function obtenerDireccionCliente(int $idCliente, int $idDireccionCliente): array
    {
        $direccion = $this->direcciones[$idDireccionCliente] ?? null;

        if ($direccion === null || $direccion['id_cliente'] !== $idCliente) {
            throw new DireccionNoEncontradaException;
        }

        return $direccion;
    }

    public function obtenerIdClientePorUsuario(int $idUsuario): int
    {
        foreach ($this->clientes as $cliente) {
            if ($cliente['id_usuario'] === $idUsuario) {
                return $cliente['id_cliente'];
            }
        }

        throw new ClienteNoEncontradoException('La cuenta no tiene un cliente asociado.');
    }

    public function registrarCliente(array $datos): int
    {
        $this->validarDatosCliente($datos, exigirContrasena: true);
        $this->validarCorreoUnico($datos['correo_electronico']);

        // Contrato 1.2.0: la dirección del registro se guarda como la principal.
        if (! is_array($datos['direccion'] ?? null)) {
            throw new DatosInvalidosException(['direccion' => 'Es obligatorio.']);
        }

        $direccion = [...$datos['direccion'], 'es_principal' => true];
        $this->validarDatosDireccion($direccion);

        $idCliente = $this->siguienteIdCliente++;

        $this->clientes[$idCliente] = [
            'id_cliente' => $idCliente,
            'id_usuario' => 1000 + $idCliente,
            'nombre' => $datos['nombre'],
            'apellidos' => $datos['apellidos'],
            'correo_electronico' => $datos['correo_electronico'],
            'telefono' => $datos['telefono'],
            'cedula' => trim($datos['cedula']),
        ];

        $this->guardarDireccion($this->siguienteIdDireccion++, $idCliente, $direccion);

        return $idCliente;
    }

    public function actualizarCliente(int $idCliente, array $datos): int
    {
        if (! isset($this->clientes[$idCliente])) {
            throw new ClienteNoEncontradoException;
        }

        $this->validarDatosCliente($datos, exigirContrasena: false);
        $this->validarCorreoUnico($datos['correo_electronico'], $idCliente);

        $this->clientes[$idCliente] = [
            ...$this->clientes[$idCliente],
            'nombre' => $datos['nombre'],
            'apellidos' => $datos['apellidos'],
            'correo_electronico' => $datos['correo_electronico'],
            'telefono' => $datos['telefono'],
        ];

        return $idCliente;
    }

    public function agregarDireccionCliente(int $idCliente, array $datos): array
    {
        if (! isset($this->clientes[$idCliente])) {
            throw new ClienteNoEncontradoException;
        }

        $this->validarDatosDireccion($datos);

        $idDireccion = $this->siguienteIdDireccion++;
        $this->guardarDireccion($idDireccion, $idCliente, $datos);

        return $this->direcciones[$idDireccion];
    }

    public function actualizarDireccionCliente(int $idCliente, int $idDireccionCliente, array $datos): array
    {
        $this->obtenerDireccionCliente($idCliente, $idDireccionCliente);
        $this->validarDatosDireccion($datos);
        $this->guardarDireccion($idDireccionCliente, $idCliente, $datos);

        return $this->direcciones[$idDireccionCliente];
    }

    public function buscarProductos(
        ?string $termino,
        ?int $idCategoria = null,
        ?int $idComercio = null,
        int $pagina = 1,
        int $porPagina = 20,
        ?int $idMarca = null,
        ?float $precioMinimo = null,
        ?float $precioMaximo = null,
        ?string $orden = null,
    ): array {
        throw $this->fueraDeAlcance();
    }

    public function listarDestacados(int $limite = 12): array
    {
        throw $this->fueraDeAlcance();
    }

    public function listarCategoriasPortal(): array
    {
        throw $this->fueraDeAlcance();
    }

    public function obtenerFichaProducto(int $idProducto): array
    {
        throw $this->fueraDeAlcance();
    }

    public function listarComercios(int $pagina = 1, int $porPagina = 20): array
    {
        throw $this->fueraDeAlcance();
    }

    public function obtenerMicrositio(int $idComercio, int $pagina = 1, int $porPagina = 20): array
    {
        throw $this->fueraDeAlcance();
    }

    /** Variación con 20 % de descuento vigente (12 990 → 10 392). */
    public const VARIACION_CON_PROMOCION = 15201;

    /** Variación sin promoción (precio normal 12 990). */
    public const VARIACION_SIN_PROMOCION = 15203;

    /** Variación que M3 no encuentra: se omite de la respuesta. */
    public const VARIACION_INEXISTENTE = 88888;

    /**
     * Precios vigentes (contrato 1.4.0) con datos fijos, para M5.
     */
    public function obtenerPreciosVigentes(array $idsVariacion): array
    {
        if (count($idsVariacion) > 100 || array_filter($idsVariacion, fn (mixed $id): bool => ! is_int($id) || $id < 1) !== []) {
            throw new DatosInvalidosException(['ids_variacion' => 'Debe tener entre 1 y 100 enteros mayores o iguales a 1.']);
        }

        $precios = [
            self::VARIACION_CON_PROMOCION => ['precio_base' => 12990.0, 'precio_final' => 10392.0, 'id_promocion' => 1, 'fin_promocion' => '2026-11-30T23:59:59+00:00'],
            self::VARIACION_SIN_PROMOCION => ['precio_base' => 12990.0, 'precio_final' => 12990.0, 'id_promocion' => null, 'fin_promocion' => null],
        ];

        $resultado = [];
        foreach ($idsVariacion as $id) {
            if (isset($precios[$id])) {
                $resultado[$id] = ['id_variacion' => $id, ...$precios[$id]];
            }
        }

        return $resultado;
    }

    /**
     * Una sola dirección principal por cliente, como exige la base de datos.
     *
     * @param  array<string, mixed>  $datos
     */
    private function guardarDireccion(int $idDireccion, int $idCliente, array $datos): void
    {
        $esPrincipal = (bool) ($datos['es_principal'] ?? false);

        if ($esPrincipal) {
            foreach ($this->direcciones as $id => $direccion) {
                if ($direccion['id_cliente'] === $idCliente) {
                    $this->direcciones[$id]['es_principal'] = false;
                }
            }
        }

        $this->direcciones[$idDireccion] = [
            'id_direccion_cliente' => $idDireccion,
            'id_cliente' => $idCliente,
            'alias_direccion' => $datos['alias_direccion'],
            'nombre_destinatario' => $datos['nombre_destinatario'],
            'telefono_destinatario' => $datos['telefono_destinatario'],
            'provincia' => $datos['provincia'],
            'canton' => $datos['canton'],
            'distrito' => $datos['distrito'],
            'otras_senas' => $datos['otras_senas'],
            'es_principal' => $esPrincipal,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarDatosCliente(array $datos, bool $exigirContrasena): void
    {
        $errores = [];

        // Mismos campos obligatorios y largos que la tabla clientes.
        foreach (['nombre' => 80, 'apellidos' => 120, 'telefono' => 20] as $campo => $maximo) {
            if (! is_string($datos[$campo] ?? null) || trim($datos[$campo]) === '') {
                $errores[$campo] = 'Es obligatorio.';
            } elseif (mb_strlen(trim($datos[$campo])) > $maximo) {
                $errores[$campo] = "No puede tener más de {$maximo} caracteres.";
            }
        }

        if ($exigirContrasena) {
            if (! is_string($datos['cedula'] ?? null) || trim($datos['cedula']) === '') {
                $errores['cedula'] = 'Es obligatorio.';
            } elseif (mb_strlen(trim($datos['cedula'])) > 20) {
                $errores['cedula'] = 'No puede tener más de 20 caracteres.';
            }
        }

        if (! is_string($datos['correo_electronico'] ?? null) || ! filter_var($datos['correo_electronico'], FILTER_VALIDATE_EMAIL)) {
            $errores['correo_electronico'] = 'No tiene un formato de correo válido.';
        }

        if ($exigirContrasena && (! is_string($datos['contrasena'] ?? null) || mb_strlen($datos['contrasena']) < 8)) {
            $errores['contrasena'] = 'Debe tener al menos 8 caracteres.';
        }

        if ($errores !== []) {
            throw new DatosInvalidosException($errores);
        }
    }

    /**
     * Correo único sin distinguir mayúsculas (RN-M1-14).
     */
    private function validarCorreoUnico(string $correo, ?int $excepto = null): void
    {
        foreach ($this->clientes as $cliente) {
            if ($cliente['id_cliente'] !== $excepto && mb_strtolower($cliente['correo_electronico']) === mb_strtolower($correo)) {
                throw new CorreoYaRegistradoException;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarDatosDireccion(array $datos): void
    {
        $errores = [];

        foreach (['alias_direccion', 'nombre_destinatario', 'telefono_destinatario', 'provincia', 'canton', 'distrito', 'otras_senas'] as $campo) {
            if (! is_string($datos[$campo] ?? null) || trim($datos[$campo]) === '') {
                $errores[$campo] = 'Es obligatorio.';
            }
        }

        if (! isset($errores['telefono_destinatario']) && ! preg_match('/^[0-9 +-]+$/', $datos['telefono_destinatario'])) {
            $errores['telefono_destinatario'] = 'Solo admite dígitos, espacios, + y -.';
        }

        if ($errores !== []) {
            throw new DatosInvalidosException($errores);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function direccion(int $id, int $idCliente, string $alias, string $otrasSenas, bool $esPrincipal): array
    {
        return [
            'id_direccion_cliente' => $id,
            'id_cliente' => $idCliente,
            'alias_direccion' => $alias,
            'nombre_destinatario' => 'Destinatario '.$alias,
            'telefono_destinatario' => '8888-0000',
            'provincia' => 'Guanacaste',
            'canton' => 'Liberia',
            'distrito' => 'Liberia',
            'otras_senas' => $otrasSenas,
            'es_principal' => $esPrincipal,
        ];
    }

    private function fueraDeAlcance(): LogicException
    {
        return new LogicException('Fuera del alcance del doble de M1: esta operación solo la usan los controladores de M1.');
    }
}
