<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

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
                'logoUrl' => 'cid:resmap-logo',
                'expirationMinutes' => config(
                    'auth.passwords.'.config('auth.defaults.passwords').'.expire'
                ),
            ])
            ->withSymfonyMessage(function (Email $message): void {
                $message->embedFromPath(public_path('images/resmap sin fondo.png'), 'resmap-logo');
            });
    }
}
