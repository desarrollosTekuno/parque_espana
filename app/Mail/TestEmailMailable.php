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
        return $this
            ->subject($this->subjectText)
            ->view('emails.test_email', [
                'subjectText' => $this->subjectText,
                'messageText' => $this->messageText,
                'entityId' => $this->entityId,
            ]);
    }
}
