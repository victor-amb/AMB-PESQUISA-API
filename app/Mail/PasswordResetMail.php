<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;
    public string $userName;

    public function __construct(string $resetUrl, string $userName)
    {
        $this->resetUrl = $resetUrl;
        $this->userName = $userName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'AMB Pesquisas - Recuperação de Senha',
        );
    }

    public function content(): Content
    {
        $body = "Olá, {$this->userName}.\n\n";
        $body .= "Recebemos uma solicitação para redefinir a senha da sua conta.\n";
        $body .= "Clique no link seguro abaixo para cadastrar uma nova senha (este link expira em 60 minutos):\n\n";
        $body .= $this->resetUrl . "\n\n";
        $body .= "Se você não solicitou a redefinição, apenas ignore este e-mail.";

        return new Content(
            htmlString: nl2br(e($body)),
        );
    }
}
