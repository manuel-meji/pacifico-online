<?php
namespace App\Models\Nucleo;

enum Rol: string
{
    case EncargadoPlataforma = 'encargado_plataforma';
    case EncargadoComercio   = 'encargado_comercio';
    case Cliente             = 'cliente';
}
