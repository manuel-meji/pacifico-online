<?php

declare(strict_types=1);

namespace Tests\Unit\Nucleo;

use App\Enums\Nucleo\RolUsuario;
use App\Models\Nucleo\Usuario;
use App\Policies\Nucleo\PolicyBaseNucleo;
use PHPUnit\Framework\TestCase;

class PolicyBaseNucleoTest extends TestCase
{
    private function policy(): object
    {
        return new class extends PolicyBaseNucleo
        {
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
        $usuario = new Usuario();
        $usuario->activo = true;

        $this->assertTrue(
            $this->policy()->activo($usuario)
        );
    }

    public function test_encargado_plataforma_es_reconocido(): void
    {
        $usuario = new Usuario();
        $usuario->activo = true;
        $usuario->rol = RolUsuario::EncargadoPlataforma;

        $this->assertTrue(
            $this->policy()->plataforma($usuario)
        );
    }

    public function test_encargado_de_comercio_solo_accede_a_su_comercio(): void
    {
        $usuario = new Usuario();
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
        $usuario = new Usuario();
        $usuario->activo = false;
        $usuario->rol = RolUsuario::EncargadoPlataforma;

        $this->assertFalse(
            $this->policy()->plataforma($usuario)
        );
    }
}