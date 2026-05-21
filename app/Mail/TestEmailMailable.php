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
        public int $entityId,
        public string $templateName = 'emails.email_template'
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
                'templateView' => $this->resolveTemplateView(),
            ]);
    }

    private function resolveTemplateView(): string
    {
        $template = trim($this->templateName);

        if ($template === '') {
            return 'emails.email_template';
        }

        if (str_contains($template, '.')) {
            return $template;
        }

        return 'emails.' . $template;
    }
}
