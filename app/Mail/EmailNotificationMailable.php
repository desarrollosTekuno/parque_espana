<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailNotificationMailable extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectText,
        public string $titleText,
        public string $bodyHtml,
        public array $attachments = []
    ) {
    }

    public function build(): self {
        $mail = $this
            ->subject($this->subjectText)
            ->view('emails.email_notification', [
                'titleText' => $this->titleText,
                'bodyHtml' => $this->bodyHtml,
            ]);

        foreach ($this->attachments as $attachment) {
            if (!isset($attachment['file_path'])) {
            } else {
                $mail->attachFromStorageDisk(
                    'public',
                    (string) $attachment['file_path'],
                    (string) ($attachment['original_name'] ?? basename((string) $attachment['file_path']))
                );
            }
        }

        return $mail;
    }
}
