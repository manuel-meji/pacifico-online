<?php

declare(strict_types=1);

namespace Tests\Unit\Nucleo;

use App\Enums\Nucleo\RolUsuario;
use App\Models\Nucleo\Usuario;
use App\Policies\Nucleo\PolicyBaseNucleo;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PolicyBaseNucleoTest extends TestCase
{
    private function policy(): object
    {
        return new class extends PolicyBaseNucleo
        {
            /** @return array{bool, bool, bool} */
            public function roles(?Authenticatable $usuario): array
            {
                return [
                    $this->esEncargadoPlataforma($usuario),
                    $this->esEncargadoComercio($usuario),
                    $this->esCliente($usuario),
                ];
            }

            public function activo(Usuario $usuario): bool
            {
                return $this->usuarioActivo($usuario);
            }

            public function plataforma(Usuario $usuario): bool
            {
                return $this->esEncargadoPlataforma($usuario);
            }

            public function comercio(
                Usuario $usuario,
                int $idComercio
            ): bool {
                return $this->perteneceAlComercio(
                    $usuario,
                    $idComercio
                );
            }
        };
    }

    public function test_usuario_activo_es_reconocido(): void
    {
        $usuario = new Usuario;
        $usuario->activo = true;

        $this->assertTrue(
            $this->policy()->activo($usuario)
        );
    }

    public function test_encargado_plataforma_es_reconocido(): void
    {
        $usuario = new Usuario;
        $usuario->activo = true;
        $usuario->rol = RolUsuario::EncargadoPlataforma;

        $this->assertTrue(
            $this->policy()->plataforma($usuario)
        );
    }

    public function test_encargado_de_comercio_solo_accede_a_su_comercio(): void
    {
        $usuario = new Usuario;
        $usuario->activo = true;
        $usuario->rol = RolUsuario::EncargadoComercio;
        $usuario->id_comercio = 7;

        $this->assertTrue(
            $this->policy()->comercio($usuario, 7)
        );

        $this->assertFalse(
            $this->policy()->comercio($usuario, 8)
        );
    }

    public function test_usuario_inactivo_no_supera_la_policy(): void
    {
        $usuario = new Usuario;
        $usuario->activo = false;
        $usuario->rol = RolUsuario::EncargadoPlataforma;

        $this->assertFalse(
            $this->policy()->plataforma($usuario)
        );
    }

    /** @param array{bool, bool, bool} $esperados */
    #[DataProvider('rolesDeUsuario')]
    public function test_verifica_los_tres_roles_y_el_estado_activo(
        RolUsuario $rol,
        bool $activo,
        array $esperados,
    ): void {
        $usuario = new Usuario(['rol' => $rol, 'activo' => $activo]);

        $this->assertSame($esperados, $this->policy()->roles($usuario));
    }

    /** @return array<string, array{RolUsuario, bool, array{bool, bool, bool}}> */
    public static function rolesDeUsuario(): array
    {
        return [
            'plataforma activa' => [RolUsuario::EncargadoPlataforma, true, [true, false, false]],
            'comercio activo' => [RolUsuario::EncargadoComercio, true, [false, true, false]],
            'cliente activo' => [RolUsuario::Cliente, true, [false, false, true]],
            'plataforma inactiva' => [RolUsuario::EncargadoPlataforma, false, [false, false, false]],
            'comercio inactivo' => [RolUsuario::EncargadoComercio, false, [false, false, false]],
            'cliente inactivo' => [RolUsuario::Cliente, false, [false, false, false]],
        ];
    }

    /** @param array{bool, bool, bool} $esperados */
    #[DataProvider('representacionesDeRol')]
    public function test_conserva_compatibilidad_con_roles_del_middleware(
        RolUsuario|string|null $rol,
        array $esperados,
    ): void {
        $usuario = new GenericUser(['rol' => $rol, 'activo' => true]);

        $this->assertSame($esperados, $this->policy()->roles($usuario));
    }

    /** @return array<string, array{RolUsuario|string|null, array{bool, bool, bool}}> */
    public static function representacionesDeRol(): array
    {
        return [
            'enum del middleware' => [RolUsuario::EncargadoPlataforma, [true, false, false]],
            'rol como texto' => ['encargado_comercio', [false, true, false]],
            'rol desconocido' => ['desconocido', [false, false, false]],
            'sin rol' => [null, [false, false, false]],
        ];
    }

    public function test_sin_usuario_no_se_autoriza_ningun_rol(): void
    {
        $this->assertSame([false, false, false], $this->policy()->roles(null));
    }
}
