<?php

namespace App\Mail;

use App\Models\Administrator\Club;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyAccessCardMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Club  $club
     * @param  Carbon  $validFrom
     * @param  Carbon  $validUntil
     * @param  array<int, string>  $cardCodes  Los card_no generados en este cobro
     */
    public function __construct(
        public Club $club,
        public Carbon $validFrom,
        public Carbon $validUntil,
        public array $cardCodes,
    ) {}

    public function build(): self
    {
         return $this
            ->subject("Tu acceso al club — {$this->club->name}")
            ->view('emails.daily_access_card', [
                'club' => $this->club,
                'validFrom' => $this->validFrom->format('d/m/Y h:i A'),
                'validUntil' => $this->validUntil->format('d/m/Y h:i A'),
                'cardCodes' => $this->cardCodes,
            ]);
    }
}