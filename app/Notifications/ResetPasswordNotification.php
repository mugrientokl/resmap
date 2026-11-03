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
            ->subject('Restablece tu contraseña · RESMAP')
            ->view('emails.auth.password-reset', [
                'name' => $notifiable->name,
                'url' => $url,
                'expirationMinutes' => config(
                    'auth.passwords.'.config('auth.defaults.passwords').'.expire'
                ),
            ]);
    }
}
