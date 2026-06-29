<?php

namespace App\Mail;

use App\Models\AdminClub\DayPass;
use App\Models\Members\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DayPassTicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DayPass $dayPass,
        public Member  $member,
    ) {}

    public function build(): self
    {
        $date = $this->dayPass->date?->format('d/m/Y') ?? '—';

        return $this
            ->subject("Tickets de acceso — Pase por día {$date}")
            ->view('emails.day_pass_ticket', [
                'dayPass' => $this->dayPass,
                'member'  => $this->member,
                'date'    => $date,
            ]);
    }
}
