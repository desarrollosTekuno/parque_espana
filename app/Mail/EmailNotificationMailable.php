<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmailNotificationMailable extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectText,
        public string $titleText,
        public string $bodyHtml,
        public array $files = []
    ) {
    }

    public function build(): self {
        $attachmentLinks = [];

        foreach ($this->files as $attachment) {
            if (!isset($attachment['file_path'])) {
                continue;
            }

            $filePath = (string) $attachment['file_path'];
            $fileName = (string) ($attachment['original_name'] ?? basename($filePath));
            $attachmentLinks[] = [
                'name' => $fileName,
                'url' => Storage::disk('public')->url($filePath),
            ];
        }

        $mail = $this
            ->subject($this->subjectText)
            ->view('emails.email_notification', [
                'titleText' => $this->titleText,
                'bodyHtml' => $this->bodyHtml,
                'attachmentLinks' => $attachmentLinks,
            ]);

        foreach ($this->files as $attachment) {
            if (!isset($attachment['file_path'])) {
                continue;
            }

            $filePath = (string) $attachment['file_path'];
            $fileName = (string) ($attachment['original_name'] ?? basename($filePath));
            $absolutePath = Storage::disk('public')->path($filePath);

            if (!is_file($absolutePath)) {
                Log::warning('Adjunto no encontrado para correo', [
                    'file_path' => $filePath,
                ]);
                continue;
            }

            $mail->attach($absolutePath, [
                'as' => $fileName,
            ]);
        }

        return $mail;
    }
}
