<?php

namespace Tests\Feature\M4_Inventario;

use App\Models\Nucleo\Usuario;

/**
 * Usuarios del núcleo para las pruebas de la API de M4 (sin guardarlos en la base,
 * igual que las pruebas de M2). El middleware EstablecerComercio toma id_comercio
 * del usuario y lo deja en ContextoComercio para el Global Scope (RN-12).
 */
trait SesionesPruebaM4
{
    protected function encargado(int $idComercio, int $idUsuario = 1): Usuario
    {
        return $this->usuarioNucleo('encargado_comercio', $idComercio, $idUsuario);
    }

    protected function cliente(int $idUsuario = 50): Usuario
    {
        return $this->usuarioNucleo('cliente', null, $idUsuario);
    }

    private function usuarioNucleo(string $rol, ?int $idComercio, int $idUsuario): Usuario
    {
        $usuario = new Usuario([
            'nombre' => 'Prueba M4',
            'correo' => "m4.{$idUsuario}@prueba.cr",
            'rol' => $rol,
            'id_comercio' => $idComercio,
            'activo' => true,
        ]);
        $usuario->setAttribute($usuario->getKeyName(), $idUsuario);

        return $usuario;
    }
}
