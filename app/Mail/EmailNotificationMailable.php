<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmailNotificationMailable extends Mailable {
    use Queueable, SerializesModels;

    private string $attachmentDisk = 'spaces';

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

            if (!Storage::disk($this->attachmentDisk)->exists($filePath)) {
                continue;
            }

            $attachmentLinks[] = [
                'name' => $fileName,
                'url' => Storage::disk($this->attachmentDisk)->temporaryUrl($filePath, now()->addMinutes(30)),
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

            if (!Storage::disk($this->attachmentDisk)->exists($filePath)) {
                Log::warning('Adjunto no encontrado para correo', [
                    'file_path' => $filePath,
                ]);
                continue;
            }

            $mail->attachData(Storage::disk($this->attachmentDisk)->get($filePath), $fileName, [
                'as' => $fileName,
                'mime' => $attachment['mime_type'] ?? null,
            ]);
        }

        return $mail;
    }
}
