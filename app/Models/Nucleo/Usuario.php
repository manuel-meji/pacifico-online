<?php

declare(strict_types=1);

namespace App\Models\Nucleo;

use App\Enums\Nucleo\RolUsuario;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Cuenta de acceso de los tres roles (PC3, secciones 5.1, 5.2 y 5.6).
 * Solo el núcleo escribe en esta tabla, mediante ServicioIdentidad::crearCuenta().
 */
class Usuario extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    /** @var list<string> */
    protected $fillable = ['nombre', 'correo', 'contrasena', 'rol', 'id_comercio', 'activo'];

    /** @var list<string> */
    protected $hidden = ['contrasena'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'rol' => RolUsuario::class,
            'activo' => 'boolean',
            'contrasena' => 'hashed',
        ];
    }

    public function getAuthPassword(): string
    {
        return (string) $this->contrasena;
    }

    public function getAuthPasswordName(): string
    {
        return 'contrasena';
    }

    /** La tabla usuarios no tiene columna remember_token. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getEmailForPasswordReset(): string
    {
        return (string) $this->correo;
    }

    public function routeNotificationForMail(): string
    {
        return (string) $this->correo;
    }
}
