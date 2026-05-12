<?php

namespace App\Mail;

use App\Models\Feedback\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FeedbackTicketCommentAddedMailable extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public function build(): self
    {
        $this->ticket->loadMissing(['status']);

        $subject = 'Nuevo comentario en tu ticket ' . $this->ticket->ticket_number;

        $latestTicketComment = $this->ticket->comments()
            ->where('is_internal', false)
            ->latest('id')
            ->value('comment');

        return $this->subject($subject)->view('emails.feedback_ticket_comment_added', [
            'ticket' => $this->ticket,
            'latestTicketComment' => $latestTicketComment,
            'subjectText' => $subject,
        ]);
    }
}
