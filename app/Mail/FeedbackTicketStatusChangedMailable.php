<?php

namespace App\Mail;

use App\Models\Feedback\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FeedbackTicketStatusChangedMailable extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public function build(): self
    {
        $this->ticket->loadMissing(['status']);

        $subject = 'Actualizacion de estatus de tu ticket ' . $this->ticket->ticket_number;

        $latestStatusComment = $this->ticket->comments()
            ->where('is_internal', false)
            ->latest('id')
            ->value('comment');

        return $this->subject($subject)->view('emails.feedback_ticket_status_changed', [
            'ticket' => $this->ticket,
            'latestStatusComment' => $latestStatusComment,
            'subjectText' => $subject,
        ]);
    }
}
