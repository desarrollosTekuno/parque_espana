<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestEmailMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectText,
        public string $messageText,
        public int $entityId
    ) {
    }

    public function build(): self
    {
        $html = '<h2>Prueba de correo SMTP</h2>'
            . '<p><strong>Entidad:</strong> ' . e((string) $this->entityId) . '</p>'
            . '<p>' . nl2br(e($this->messageText)) . '</p>';

        return $this
            ->subject($this->subjectText)
            ->html($html);
    }
}
