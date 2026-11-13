<?php

namespace Tests\Feature\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\ClienteNoEncontradoException;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Services\M1_Portal\DTO\CategoriaDTO;
use App\Services\M1_Portal\DTO\ClienteDTO;
use App\Services\M1_Portal\DTO\ComercioDTO;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\M1_Portal\DTO\DestacadosDTO;
use App\Services\M1_Portal\DTO\DireccionClienteDTO;
use App\Services\M1_Portal\DTO\Disponibilidad;
use App\Services\M1_Portal\DTO\EstadoComercio;
use App\Services\M1_Portal\DTO\FichaProductoDTO;
use App\Services\M1_Portal\DTO\ListadoComerciosDTO;
use App\Services\M1_Portal\DTO\MicrositioDTO;
use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use App\Services\M1_Portal\DTO\ResultadoBusquedaDTO;
use App\Services\M1_Portal\DTO\VariacionDTO;
use App\Services\M1_Portal\PortalPublicoService;
use ReflectionMethod;
use Symfony\Component\Yaml\Yaml;

/**
 * La fachada PortalPublicoService delega en el servicio de cada caso de uso y
 * devuelve arreglos que cumplen los esquemas del contrato. Los servicios de
 * los casos de uso los hacen otros integrantes de SG1; aquí se reemplazan por
 * servicios de prueba enlazados con el nombre de su clase.
 */
class PortalPublicoServiceTest extends PruebaM1
{
    /** @var array<string, mixed>|null */
    private static ?array $esquemas = null;

    public function test_el_contenedor_entrega_la_fachada_para_el_contrato(): void
    {
        $this->assertInstanceOf(PortalPublicoService::class, app(PortalPublicoContract::class));
    }

    // ---------------------------------------------------------------
    // Servicios que no existen o no están disponibles
    // ---------------------------------------------------------------

    /**
     * Se usa un nombre de clase que nunca va a existir, así la prueba no
     * depende de qué servicios de SG1 ya estén subidos.
     */
    public function test_una_operacion_cuyo_servicio_no_existe_lanza_err_006(): void
    {
        $servicio = new ReflectionMethod(PortalPublicoService::class, 'servicio');

        $this->expectException(DependenciaNoDisponibleException::class);

        $servicio->invoke(app(PortalPublicoContract::class), 'App\\Services\\M1_Portal\\ServicioQueNoExiste');
    }

    public function test_listar_destacados_no_falla_si_el_servicio_no_esta_disponible_y_marca_las_secciones(): void
    {
        $this->enlazar(PortalPublicoService::SERVICIO_PORTADA, $this->servicioNoDisponible());

        $destacados = app(PortalPublicoContract::class)->listarDestacados();

        $this->assertSame(['comercios' => [], 'productos' => [], 'secciones_no_disponibles' => ['comercios', 'productos']], $destacados);
        $this->assertCumpleEsquema('Destacados', $destacados);
    }

    public function test_un_servicio_no_disponible_no_afecta_a_los_demas(): void
    {
        $this->enlazar(PortalPublicoService::SERVICIO_FICHA, new class
        {
            public function obtenerFicha(int $idProducto): FichaProductoDTO
            {
                return PortalPublicoServiceTest::ficha($idProducto);
            }
        });
        $this->enlazar(PortalPublicoService::SERVICIO_MICROSITIO, $this->servicioNoDisponible());

        $this->assertSame(1520, app(PortalPublicoContract::class)->obtenerFichaProducto(1520)['id_producto']);

        $this->expectException(DependenciaNoDisponibleException::class);
        app(PortalPublicoContract::class)->listarComercios();
    }

    // ---------------------------------------------------------------
    // Navegación
    // ---------------------------------------------------------------

    public function test_buscar_productos_pasa_el_criterio_completo_y_devuelve_resultado_busqueda(): void
    {
        $servicio = new class
        {
            public ?CriterioBusquedaDTO $recibido = null;

            public function buscar(CriterioBusquedaDTO $criterio): ResultadoBusquedaDTO
            {
                $this->recibido = $criterio;

                return new ResultadoBusquedaDTO([PortalPublicoServiceTest::producto()], 41, $criterio->pagina, $criterio->porPagina);
            }
        };
        $this->enlazar(PortalPublicoService::SERVICIO_BUSQUEDA, $servicio);

        $resultado = app(PortalPublicoContract::class)->buscarProductos('café', 10, 34, 3, 12, 7, 1000.0, 9000.0, 'precio_asc');

        $this->assertEquals(new CriterioBusquedaDTO('café', 10, 34, 7, 1000.0, 9000.0, 'precio_asc', 3, 12), $servicio->recibido);
        $this->assertCumpleEsquema('ResultadoBusqueda', $resultado);
        $this->assertSame(['pagina' => 3, 'por_pagina' => 12, 'total_resultados' => 41], array_slice($resultado, 0, 3));
        $this->assertSame([
            'id_producto' => 1520,
            'nombre' => 'Café molido 500 g',
            'imagen_principal' => 'https://img.example/cafe.jpg',
            'precio' => 4500.0,
            'precio_hasta' => 6200.0,
            'moneda' => 'CRC',
            'disponibilidad' => 'Disponible',
            'comercio' => ['id_comercio' => 34, 'nombre_comercial' => 'Café Nicoyano'],
        ], $resultado['productos'][0]);
    }

    public function test_listar_categorias_arma_el_menu_con_subcategorias_en_el_orden_recibido(): void
    {
        $this->enlazar(PortalPublicoService::SERVICIO_BUSQUEDA, new class
        {
            /**
             * @return list<CategoriaDTO>
             */
            public function listarCategoriasPortal(): array
            {
                return [
                    new CategoriaDTO(20, 'Artesanía', null, 'https://img.example/artesania.svg', 1),
                    new CategoriaDTO(21, 'Cerámica', 20),
                    new CategoriaDTO(10, 'Alimentos', null, null, 2),
                    new CategoriaDTO(99, 'Huérfana', 500),
                    new CategoriaDTO(11, 'Café', 10),
                    new CategoriaDTO(22, 'Tejidos', 20),
                ];
            }
        });

        $menu = app(PortalPublicoContract::class)->listarCategoriasPortal();

        $this->assertSame([
            [
                'id_categoria' => 20,
                'nombre' => 'Artesanía',
                'icono_url' => 'https://img.example/artesania.svg',
                'orden_visualizacion' => 1,
                'subcategorias' => [['id_categoria' => 21, 'nombre' => 'Cerámica'], ['id_categoria' => 22, 'nombre' => 'Tejidos']],
            ],
            [
                'id_categoria' => 10,
                'nombre' => 'Alimentos',
                'icono_url' => null,
                'orden_visualizacion' => 2,
                'subcategorias' => [['id_categoria' => 11, 'nombre' => 'Café']],
            ],
        ], $menu);

        foreach ($menu as $categoria) {
            $this->assertCumpleEsquema('CategoriaPortal', $categoria);
        }
    }

    public function test_listar_destacados_convierte_productos_y_comercios(): void
    {
        $this->enlazar(PortalPublicoService::SERVICIO_PORTADA, new class
        {
            public ?int $limite = null;

            public function listarDestacados(int $limite): DestacadosDTO
            {
                $this->limite = $limite;

                return new DestacadosDTO([PortalPublicoServiceTest::producto()], [PortalPublicoServiceTest::comercio()], ['comercios']);
            }
        });

        $destacados = app(PortalPublicoContract::class)->listarDestacados(4);

        $this->assertSame(4, app(PortalPublicoService::SERVICIO_PORTADA)->limite);
        $this->assertCumpleEsquema('Destacados', $destacados);
        $this->assertSame([['id_comercio' => 34, 'nombre_comercial' => 'Café Nicoyano', 'logotipo' => 'https://img.example/logo.png']], $destacados['comercios']);
        $this->assertSame(['comercios'], $destacados['secciones_no_disponibles']);
    }

    public function test_obtener_ficha_producto_cumple_el_esquema_ficha_producto(): void
    {
        $this->enlazar(PortalPublicoService::SERVICIO_FICHA, new class
        {
            public function obtenerFicha(int $idProducto): FichaProductoDTO
            {
                return PortalPublicoServiceTest::ficha($idProducto);
            }
        });

        $ficha = app(PortalPublicoContract::class)->obtenerFichaProducto(1520);

        $this->assertCumpleEsquema('FichaProducto', $ficha);
        $this->assertSame(['id_comercio' => 34, 'nombre_comercial' => 'Café Nicoyano'], $ficha['comercio']);
        $this->assertSame('Agotado', $ficha['variaciones'][0]['disponibilidad']);
        $this->assertFalse($ficha['variaciones'][0]['comprable']);
        $this->assertSame([['tipo_atributo' => 'Molienda', 'valor' => 'Fina']], $ficha['variaciones'][0]['atributos']);
    }

    public function test_listar_comercios_y_micrositio_cumplen_sus_esquemas(): void
    {
        $this->enlazar(PortalPublicoService::SERVICIO_MICROSITIO, new class
        {
            public function listarComercios(int $pagina, int $porPagina): ListadoComerciosDTO
            {
                return new ListadoComerciosDTO([PortalPublicoServiceTest::comercio()], 1, $pagina, $porPagina);
            }

            public function obtenerMicrositio(int $idComercio, int $pagina, int $porPagina): MicrositioDTO
            {
                return new MicrositioDTO(
                    PortalPublicoServiceTest::comercio(),
                    new ResultadoBusquedaDTO([PortalPublicoServiceTest::producto()], 1, $pagina, $porPagina),
                );
            }
        });

        $listado = app(PortalPublicoContract::class)->listarComercios(2, 5);
        $micrositio = app(PortalPublicoContract::class)->obtenerMicrositio(34, 1, 10);

        $this->assertCumpleEsquema('ListadoComercios', $listado);
        $this->assertSame(['pagina' => 2, 'por_pagina' => 5, 'total' => 1], array_slice($listado, 0, 3));
        $this->assertCumpleEsquema('Micrositio', $micrositio);
        $this->assertSame('https://img.example/logo.png', $micrositio['comercio']['logo_url']);
        $this->assertSame([['imagen_url' => 'https://img.example/local.jpg', 'texto_alternativo' => 'Café Nicoyano']], $micrositio['comercio']['galeria']);
        $this->assertSame(10, $micrositio['productos']['por_pagina']);
    }

    // ---------------------------------------------------------------
    // Clientes y direcciones
    // ---------------------------------------------------------------

    public function test_obtener_cliente_y_direcciones_cumplen_sus_esquemas(): void
    {
        $this->enlazar(PortalPublicoService::SERVICIO_CLIENTE, $this->servicioCliente());
        $portal = app(PortalPublicoContract::class);

        $cliente = $portal->obtenerCliente(5);
        $direcciones = $portal->listarDireccionesCliente(5);

        $this->assertCumpleEsquema('Cliente', $cliente);
        $this->assertSame(['id_cliente', 'nombre', 'apellidos', 'correo_electronico', 'telefono', 'direccion_envio', 'identificacion_tributaria'], array_keys($cliente));
        $this->assertCumpleEsquema('DireccionCliente', $direcciones[0]);
        $this->assertCumpleEsquema('DireccionCliente', $portal->obtenerDireccionCliente(5, 11));
        $this->assertSame(5, $portal->obtenerIdClientePorUsuario(501));
    }

    public function test_las_excepciones_del_servicio_llegan_sin_cambios(): void
    {
        $this->enlazar(PortalPublicoService::SERVICIO_CLIENTE, $this->servicioCliente());

        $this->expectException(ClienteNoEncontradoException::class);

        app(PortalPublicoContract::class)->obtenerCliente(999);
    }

    public function test_registrar_cliente_entrega_el_dto_y_la_contrasena_al_servicio(): void
    {
        $servicio = $this->servicioCliente();
        $this->enlazar(PortalPublicoService::SERVICIO_CLIENTE, $servicio);

        $id = app(PortalPublicoContract::class)->registrarCliente([
            ...$this->datosCliente(),
            'correo_electronico' => '  nueva@example.com ',
            'cedula' => '5-0123-0456',
            'contrasena' => 'secreta123',
            'direccion' => [...$this->datosDireccion(), 'es_principal' => false],
        ]);

        $this->assertSame(80, $id);
        $this->assertTrue($servicio->direccionRegistrada->esPrincipal);
        $this->assertNull($servicio->direccionRegistrada->idCliente);
        $this->assertSame('Liberia', $servicio->direccionRegistrada->canton);
        $this->assertSame('', $servicio->registrado->direccionEnvio);
        $this->assertSame('secreta123', $servicio->contrasena);
        $this->assertNull($servicio->registrado->idCliente);
        $this->assertSame('nueva@example.com', $servicio->registrado->correoElectronico);
        $this->assertSame('5-0123-0456', $servicio->registrado->cedula);
    }

    public function test_registrar_cliente_rechaza_datos_incompletos_con_detalle_por_campo(): void
    {
        $this->enlazar(PortalPublicoService::SERVICIO_CLIENTE, $this->servicioCliente());

        try {
            app(PortalPublicoContract::class)->registrarCliente([
                ...$this->datosCliente(),
                'nombre' => '  ',
                'correo_electronico' => 'no-es-correo',
                'telefono' => str_repeat('8', 21),
            ]);
            $this->fail('Debió lanzar DatosInvalidosException.');
        } catch (DatosInvalidosException $excepcion) {
            $this->assertSame([
                'nombre' => 'Es obligatorio.',
                'telefono' => 'No puede tener más de 20 caracteres.',
                'correo_electronico' => 'No tiene un formato de correo válido.',
                'cedula' => 'Es obligatorio.',
                'contrasena' => 'Es obligatorio.',
                'direccion' => 'Es obligatorio.',
            ], $excepcion->detalles());
        }
    }

    public function test_registrar_cliente_respeta_los_largos_de_la_tabla_clientes(): void
    {
        $this->enlazar(PortalPublicoService::SERVICIO_CLIENTE, $this->servicioCliente());

        try {
            app(PortalPublicoContract::class)->registrarCliente([
                ...$this->datosCliente(),
                'nombre' => str_repeat('a', 81),
                'apellidos' => str_repeat('b', 121),
                'cedula' => str_repeat('1', 21),
                'contrasena' => 'secreta123',
                'direccion' => $this->datosDireccion(),
            ]);
            $this->fail('Debió lanzar DatosInvalidosException.');
        } catch (DatosInvalidosException $excepcion) {
            $this->assertSame([
                'nombre' => 'No puede tener más de 80 caracteres.',
                'apellidos' => 'No puede tener más de 120 caracteres.',
                'cedula' => 'No puede tener más de 20 caracteres.',
            ], $excepcion->detalles());
        }
    }

    public function test_registrar_cliente_acepta_los_largos_maximos_de_la_tabla(): void
    {
        $servicio = $this->servicioCliente();
        $this->enlazar(PortalPublicoService::SERVICIO_CLIENTE, $servicio);

        app(PortalPublicoContract::class)->registrarCliente([
            ...$this->datosCliente(),
            'nombre' => str_repeat('a', 80),
            'apellidos' => str_repeat('b', 120),
            'cedula' => str_repeat('1', 20),
            'contrasena' => 'secreta123',
            'direccion' => $this->datosDireccion(),
        ]);

        $this->assertSame(str_repeat('a', 80), $servicio->registrado->nombre);
        $this->assertSame(str_repeat('1', 20), $servicio->registrado->cedula);
    }

    public function test_registrar_cliente_exige_telefono_y_contrasena_de_al_menos_8_caracteres(): void
    {
        $this->enlazar(PortalPublicoService::SERVICIO_CLIENTE, $this->servicioCliente());

        try {
            app(PortalPublicoContract::class)->registrarCliente([
                ...$this->datosCliente(),
                'telefono' => ' ',
                'cedula' => '5-0123-0456',
                'contrasena' => 'corta12',
                'direccion' => $this->datosDireccion(),
            ]);
            $this->fail('Debió lanzar DatosInvalidosException.');
        } catch (DatosInvalidosException $excepcion) {
            $this->assertSame([
                'telefono' => 'Es obligatorio.',
                'contrasena' => 'Debe tener al menos 8 caracteres.',
            ], $excepcion->detalles());
        }
    }

    public function test_actualizar_cliente_conserva_la_cedula_que_ya_tiene(): void
    {
        $servicio = $this->servicioCliente();
        $this->enlazar(PortalPublicoService::SERVICIO_CLIENTE, $servicio);

        $id = app(PortalPublicoContract::class)->actualizarCliente(5, [...$this->datosCliente(), 'nombre' => 'Ana María']);

        $this->assertSame(5, $id);
        $this->assertSame('Ana María', $servicio->actualizado->nombre);
        $this->assertSame('5-0123-0456', $servicio->actualizado->cedula);
        $this->assertSame(5, $servicio->actualizado->idCliente);
    }

    public function test_actualizar_cliente_no_toca_la_direccion_principal(): void
    {
        $servicio = $this->servicioCliente();
        $this->enlazar(PortalPublicoService::SERVICIO_CLIENTE, $servicio);

        app(PortalPublicoContract::class)->actualizarCliente(5, [
            ...$this->datosCliente(),
            'direccion_envio' => 'Otra dirección que se ignora',
        ]);

        $this->assertSame('200 m norte del parque, Liberia, Liberia, Guanacaste', $servicio->actualizado->direccionEnvio);
    }

    public function test_registrar_cliente_valida_la_direccion_con_los_largos_y_formato_de_la_tabla(): void
    {
        $this->enlazar(PortalPublicoService::SERVICIO_CLIENTE, $this->servicioCliente());

        try {
            app(PortalPublicoContract::class)->registrarCliente([
                ...$this->datosCliente(),
                'cedula' => '5-0123-0456',
                'contrasena' => 'secreta123',
                'direccion' => [
                    ...$this->datosDireccion(),
                    'alias_direccion' => str_repeat('a', 41),
                    'provincia' => ' ',
                    'telefono_destinatario' => 'llamar al 8888',
                ],
            ]);
            $this->fail('Debió lanzar DatosInvalidosException.');
        } catch (DatosInvalidosException $excepcion) {
            $this->assertSame([
                'direccion.alias_direccion' => 'No puede tener más de 40 caracteres.',
                'direccion.provincia' => 'Es obligatorio.',
                'direccion.telefono_destinatario' => 'Solo admite dígitos, espacios, + y -.',
            ], $excepcion->detalles());
        }
    }

    public function test_agregar_y_actualizar_direccion_validan_y_devuelven_direccion_cliente(): void
    {
        $this->enlazar(PortalPublicoService::SERVICIO_CLIENTE, $this->servicioCliente());
        $portal = app(PortalPublicoContract::class);

        $nueva = $portal->agregarDireccionCliente(5, $this->datosDireccion());
        $editada = $portal->actualizarDireccionCliente(5, 11, [...$this->datosDireccion(), 'es_principal' => true]);

        $this->assertCumpleEsquema('DireccionCliente', $nueva);
        $this->assertFalse($nueva['es_principal']);
        $this->assertSame(11, $editada['id_direccion_cliente']);
        $this->assertTrue($editada['es_principal']);

        $this->expectException(DatosInvalidosException::class);
        $portal->agregarDireccionCliente(5, ['alias_direccion' => 'Casa']);
    }

    // ---------------------------------------------------------------
    // Datos de prueba
    // ---------------------------------------------------------------

    public static function producto(): ProductoResumenDTO
    {
        return new ProductoResumenDTO(1520, 34, 'Café molido 500 g', 'https://img.example/cafe.jpg', 4500.0, 6200.0, 'CRC', 'Café Nicoyano', Disponibilidad::Disponible);
    }

    public static function comercio(): ComercioDTO
    {
        return new ComercioDTO(
            idComercio: 34,
            nombreComercial: 'Café Nicoyano',
            logotipo: 'https://img.example/logo.png',
            descripcion: 'Café de altura de Nicoya.',
            imagenes: ['https://img.example/local.jpg'],
            horarios: [['dia_semana' => 1, 'hora_apertura' => '08:00', 'hora_cierre' => '17:00', 'cerrado' => false]],
            contacto: ['telefono' => '2685-0000', 'correo' => 'hola@example.com', 'direccion' => 'Nicoya centro'],
            redesSociales: [['plataforma' => 'instagram', 'url' => 'https://instagram.example/cafe']],
            politicas: [['tipo' => 'devoluciones', 'descripcion' => '8 días.']],
            estado: EstadoComercio::Activo,
        );
    }

    public static function ficha(int $idProducto): FichaProductoDTO
    {
        return new FichaProductoDTO(
            idProducto: $idProducto,
            nombre: 'Café molido 500 g',
            descripcion: null,
            marca: 'Nicoyano',
            videoUrl: null,
            moneda: 'CRC',
            imagenes: ['https://img.example/cafe.jpg'],
            variaciones: [new VariacionDTO(3001, 'CAF-500-F', [['tipo_atributo' => 'Molienda', 'valor' => 'Fina']], 4500.0, [], true, Disponibilidad::Agotado, false)],
            comercio: self::comercio(),
            relacionados: [self::producto()],
            compraHabilitada: true,
        );
    }

    /**
     * Servicio de clientes de prueba con el cliente 5 (cuenta 501) y su
     * dirección 11. Guarda lo que recibe para revisarlo.
     */
    private function servicioCliente(): object
    {
        return new class
        {
            public ?ClienteDTO $registrado = null;

            public ?string $contrasena = null;

            public ?ClienteDTO $actualizado = null;

            public ?DireccionClienteDTO $direccionRegistrada = null;

            public function obtener(int $idCliente): ClienteDTO
            {
                if ($idCliente !== 5) {
                    throw new ClienteNoEncontradoException;
                }

                return new ClienteDTO(5, 'Ana', 'Mora Rojas', 'ana@example.com', '8888-0000', '5-0123-0456', '200 m norte del parque, Liberia, Liberia, Guanacaste');
            }

            public function obtenerIdPorUsuario(int $idUsuario): int
            {
                return 5;
            }

            public function registrar(ClienteDTO $cliente, DireccionClienteDTO $direccion, string $contrasena): int
            {
                $this->registrado = $cliente;
                $this->direccionRegistrada = $direccion;
                $this->contrasena = $contrasena;

                return 80;
            }

            public function actualizar(int $idCliente, ClienteDTO $cliente): int
            {
                $this->actualizado = $cliente;

                return $idCliente;
            }

            /**
             * @return list<DireccionClienteDTO>
             */
            public function listarDirecciones(int $idCliente): array
            {
                return [$this->obtenerDireccion($idCliente, 11)];
            }

            public function obtenerDireccion(int $idCliente, int $idDireccionCliente): DireccionClienteDTO
            {
                return new DireccionClienteDTO($idDireccionCliente, $idCliente, 'Casa', 'Ana Mora', '8888-0000', 'Guanacaste', 'Liberia', 'Liberia', '200 m norte del parque', true);
            }

            public function agregarDireccion(int $idCliente, DireccionClienteDTO $direccion): DireccionClienteDTO
            {
                $direccion->idDireccionCliente = 12;

                return $direccion;
            }

            public function actualizarDireccion(int $idCliente, int $idDireccionCliente, DireccionClienteDTO $direccion): DireccionClienteDTO
            {
                return $direccion;
            }
        };
    }

    /**
     * @return array<string, string>
     */
    private function datosCliente(): array
    {
        return [
            'nombre' => 'Ana',
            'apellidos' => 'Mora Rojas',
            'correo_electronico' => 'ana@example.com',
            'telefono' => '8888-0000',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function datosDireccion(): array
    {
        return [
            'alias_direccion' => 'Trabajo',
            'nombre_destinatario' => 'Ana Mora',
            'telefono_destinatario' => '8888-0000',
            'provincia' => 'Guanacaste',
            'canton' => 'Liberia',
            'distrito' => 'Liberia',
            'otras_senas' => 'Frente al hospital',
        ];
    }

    /**
     * Servicio cuyo proveedor está caído: cualquier método lanza ERR-006.
     */
    private function servicioNoDisponible(): object
    {
        return new class
        {
            /**
             * @param  array<int, mixed>  $argumentos
             */
            public function __call(string $metodo, array $argumentos): never
            {
                throw new DependenciaNoDisponibleException;
            }
        };
    }

    private function enlazar(string $clase, object $servicio): void
    {
        $this->app->instance($clase, $servicio);
    }

    // ---------------------------------------------------------------
    // Validación contra los esquemas del contrato
    // ---------------------------------------------------------------

    /**
     * Revisa que el arreglo tenga los campos obligatorios del esquema, ningún
     * campo que el esquema no declare y valores del tipo declarado.
     */
    private function assertCumpleEsquema(string $nombre, mixed $valor): void
    {
        $this->revisar(['$ref' => '#/components/schemas/'.$nombre], $valor, $nombre);
    }

    /**
     * @param  array<string, mixed>  $esquema
     */
    private function revisar(array $esquema, mixed $valor, string $ruta): void
    {
        $esquema = $this->resolver($esquema);

        if ($valor === null) {
            $this->assertTrue($esquema['nullable'] ?? false, "{$ruta} no puede ser null.");

            return;
        }

        if (isset($esquema['properties']) || ($esquema['type'] ?? null) === 'object') {
            $this->assertIsArray($valor, "{$ruta} debe ser objeto.");
            $propiedades = $esquema['properties'] ?? [];

            foreach ($esquema['required'] ?? [] as $campo) {
                $this->assertArrayHasKey($campo, $valor, "{$ruta} no trae {$campo}.");
            }

            foreach ($valor as $campo => $contenido) {
                $this->assertArrayHasKey($campo, $propiedades, "{$ruta}.{$campo} no está en el esquema.");
                $this->revisar($propiedades[$campo], $contenido, "{$ruta}.{$campo}");
            }

            return;
        }

        match ($esquema['type'] ?? null) {
            'array' => (function () use ($esquema, $valor, $ruta): void {
                $this->assertIsList($valor, "{$ruta} debe ser lista.");
                foreach ($valor as $indice => $elemento) {
                    $this->revisar($esquema['items'] ?? [], $elemento, "{$ruta}[{$indice}]");
                }
            })(),
            'integer' => $this->assertIsInt($valor, "{$ruta} debe ser entero."),
            'number' => $this->assertTrue(is_int($valor) || is_float($valor), "{$ruta} debe ser número."),
            'boolean' => $this->assertIsBool($valor, "{$ruta} debe ser booleano."),
            'string' => $this->assertIsString($valor, "{$ruta} debe ser texto."),
            default => null,
        };

        if (isset($esquema['enum'])) {
            $this->assertContains($valor, $esquema['enum'], "{$ruta} no es un valor permitido.");
        }
    }

    /**
     * Sigue los $ref y une los allOf del esquema.
     *
     * @param  array<string, mixed>  $esquema
     * @return array<string, mixed>
     */
    private function resolver(array $esquema): array
    {
        self::$esquemas ??= Yaml::parseFile(base_path('contracts/m1-portal/v1/openapi.yaml'))['components']['schemas'];

        while (isset($esquema['$ref'])) {
            $esquema = self::$esquemas[substr($esquema['$ref'], strlen('#/components/schemas/'))];
        }

        if (isset($esquema['allOf'])) {
            $unido = ['type' => 'object', 'properties' => [], 'required' => []];

            foreach ($esquema['allOf'] as $parte) {
                $parte = $this->resolver($parte);
                $unido['properties'] += $parte['properties'] ?? [];
                $unido['required'] = [...$unido['required'], ...$parte['required'] ?? []];
            }

            return $unido;
        }

        return $esquema;
    }
}
