<?php

namespace App\Mail;

use App\Models\Feedback\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FeedbackTicketResolvedMailable extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public function build(): self
    {
        $this->ticket->loadMissing(['status']);

        $subject = 'Tu ticket fue resuelto ' . $this->ticket->ticket_number;

        $resolutionMessage = trim((string) ($this->ticket->resolution_notes ?? ''));

        if ($resolutionMessage === '') {
            $resolutionMessage = $this->ticket->comments()
                ->where('is_internal', false)
                ->latest('id')
                ->value('comment');
        }

        return $this->subject($subject)->view('emails.feedback_ticket_resolved', [
            'ticket' => $this->ticket,
            'resolutionMessage' => $resolutionMessage,
            'subjectText' => $subject,
        ]);
    }
}
