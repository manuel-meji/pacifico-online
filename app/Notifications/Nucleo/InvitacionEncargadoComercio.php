<?php

declare(strict_types=1);

namespace App\Notifications\Nucleo;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitación para que un Encargado del comercio defina su contraseña (PC3, 5.1 y 5.6).
 * Se encola y se envía después de confirmar la transacción que creó la cuenta.
 */
class InvitacionEncargadoComercio extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // La ruta de la pantalla para definir la contraseña la define el frontend (pendiente).
        $url = url('/crear-contrasena').'?token='.$this->token.'&correo='.urlencode((string) $notifiable->correo);

        return (new MailMessage)
            ->subject('Invitación a Pacíficos Online')
            ->greeting('Hola, '.$notifiable->nombre)
            ->line('Tu comercio fue aprobado en Pacíficos Online. Define tu contraseña para ingresar.')
            ->action('Definir contraseña', $url);
    }
}
