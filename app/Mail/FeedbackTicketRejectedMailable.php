<?php

namespace App\Mail;

use App\Models\Feedback\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FeedbackTicketRejectedMailable extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public function build(): self
    {
        $this->ticket->loadMissing(['status']);

        $subject = 'Tu ticket fue rechazado ' . $this->ticket->ticket_number;

        $rejectionMessage = trim((string) ($this->ticket->rejection_reason ?? ''));

        if ($rejectionMessage === '') {
            $rejectionMessage = $this->ticket->comments()
                ->where('is_internal', false)
                ->latest('id')
                ->value('comment');
        }

        return $this->subject($subject)->view('emails.feedback_ticket_rejected', [
            'ticket' => $this->ticket,
            'rejectionMessage' => $rejectionMessage,
            'subjectText' => $subject,
        ]);
    }
}
