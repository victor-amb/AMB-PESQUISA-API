<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlatformInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $customMessage;
    public string $setupUrl;

    public function __construct(string $customMessage, string $setupUrl)
    {
        $this->customMessage = $customMessage;
        $this->setupUrl = $setupUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'AMB Pesquisas - Convite de Ingresso na Plataforma',
        );
    }

    public function content(): Content
    {
        // Monta o e-mail concatenando a mensagem customizada do gestor com o link de ativação
        $body = $this->customMessage . "\n\nClique no link seguro abaixo para concluir seu perfil e criar sua senha:\n" . $this->setupUrl;

        return new Content(
            htmlString: nl2br(e($body)),
        );
    }
}