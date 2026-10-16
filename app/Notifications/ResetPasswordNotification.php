<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPasswordNotification
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Restablecer Contraseña - RESMAP')
            ->greeting('Hola,')
            ->line('Recibimos una solicitud para restablecer tu contraseña.')
            ->line('Este enlace expirará en 60 minutos.')
            ->action('Restablecer Contraseña', $url)
            ->line('Si no solicitaste un restablecimiento de contraseña, ignora este correo.')
            ->line('')
            ->line('Saludos,')
            ->line('RESMAP');
    }
}
