<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class MailtrapTest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $recipient) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Prueba de correo · RESMAP-APP',
            using: [
                fn (Email $message) => $message->embedFromPath(public_path('images/resmap sin fondo.png'), 'resmap-logo'),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mailtrap-test',
            with: [
                'recipient' => $this->recipient,
                'logoUrl' => 'cid:resmap-logo',
            ],
        );
    }
}
