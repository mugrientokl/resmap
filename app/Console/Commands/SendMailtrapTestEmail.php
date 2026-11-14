<?php

namespace App\Console\Commands;

use App\Mail\MailtrapTest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('mailtrap:test {recipient : Dirección de destino para el correo de prueba}')]
#[Description('Envía un correo de prueba al Mailtrap Email Sandbox')]
class SendMailtrapTestEmail extends Command
{
    public function handle(): int
    {
        $recipient = $this->argument('recipient');
        $smtp = config('mail.mailers.smtp');

        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Ingresa una dirección de correo válida.');

            return self::FAILURE;
        }

        if (config('mail.default') !== 'smtp'
            || blank($smtp['username'] ?? null)
            || blank($smtp['password'] ?? null)
        ) {
            $this->error('Configura MAIL_MAILER=smtp y completa MAIL_USERNAME y MAIL_PASSWORD en .env.');

            return self::FAILURE;
        }

        try {
            Mail::mailer('smtp')->to($recipient)->send(new MailtrapTest($recipient));
        } catch (Throwable) {
            $this->error('No se pudo enviar el correo. Revisa host, puerto, TLS y credenciales SMTP.');

            return self::FAILURE;
        }

        $this->info('Correo enviado al Mailtrap Email Sandbox. Revisa la bandeja My Sandbox.');

        return self::SUCCESS;
    }
}
