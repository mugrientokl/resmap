<?php

namespace Tests\Feature;

use App\Mail\MailtrapTest;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailtrapTestCommandTest extends TestCase
{
    public function test_command_sends_the_branded_message_using_the_smtp_mailer(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.username' => 'sandbox-user',
            'mail.mailers.smtp.password' => 'sandbox-password',
        ]);

        $this->artisan('mailtrap:test', ['recipient' => 'sandbox@example.test'])
            ->expectsOutput('Correo enviado al Mailtrap Email Sandbox. Revisa la bandeja My Sandbox.')
            ->assertExitCode(0);

        Mail::assertSent(MailtrapTest::class, 'sandbox@example.test');

        $sentMail = Mail::sent(MailtrapTest::class)->first();
        $this->assertNotNull($sentMail);
        $this->assertStringContainsString('RESMAP-APP', $sentMail->render());
    }

    public function test_command_does_not_send_when_smtp_credentials_are_missing(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
        ]);

        $this->artisan('mailtrap:test', ['recipient' => 'sandbox@example.test'])
            ->expectsOutput('Configura MAIL_MAILER=smtp y completa MAIL_USERNAME y MAIL_PASSWORD en .env.')
            ->assertExitCode(1);

        Mail::assertNothingSent();
    }
}
