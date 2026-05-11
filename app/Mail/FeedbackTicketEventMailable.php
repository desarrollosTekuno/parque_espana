<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FeedbackTicketEventMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectText,
        public string $headerText,
        public string $messageText,
        public array $ticketData,
        public string $templateName = 'emails.email_template'
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject($this->subjectText)
            ->view('emails.feedback_ticket_event', [
                'subjectText' => $this->subjectText,
                'headerText' => $this->headerText,
                'messageText' => $this->messageText,
                'ticketData' => $this->ticketData,
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
