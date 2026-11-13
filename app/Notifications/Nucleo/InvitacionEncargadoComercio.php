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
        // Pantalla del portal (frontend) que envía el formulario a POST /crear-contrasena.
        $url = rtrim((string) config('app.frontend_url'), '/').'/app.html?'.http_build_query([
            'pantalla' => 'crear-contrasena',
            'token' => $this->token,
            'correo' => (string) $notifiable->correo,
        ]);

        return (new MailMessage)
            ->subject('Invitación a Pacíficos Online')
            ->greeting('Hola, '.$notifiable->nombre)
            ->line('Tu comercio fue aprobado en Pacíficos Online. Define tu contraseña para ingresar.')
            ->action('Definir contraseña', $url);
    }
}
