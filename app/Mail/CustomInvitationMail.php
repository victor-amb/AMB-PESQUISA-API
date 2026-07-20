<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $customMessage;

    public function __with($customMessage)
    {
        $this->customMessage = $customMessage;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'AMB Pesquisas - Convite de Participação Institucional',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: nl2br(e($this->customMessage)), // Renderiza quebras de linha enviadas do editor de texto do front
        );
    }
}