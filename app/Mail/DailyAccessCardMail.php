<?php

namespace App\Mail;

use App\Models\Administrator\Club;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DailyAccessCardMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Club  $club
     * @param  array<int, string>  $cardCodes  Los card_no generados en este cobro
     */
    public function __construct(
        public Club $club,
        public array $cardCodes,
    ) {}

    public function build(): self
    {
         return $this
            ->subject("Tu acceso al club — {$this->club->name}")
            ->view('emails.daily_access_card', [
                'club' => $this->club,
                'cardCodes' => $this->cardCodes,
                'parkName' => $this->club->name,
                'eyebrow' => 'Pase de acceso',
                'parkLogo' => $this->club->logo_path
                ? Storage::disk('spaces')->url($this->club->logo_path)
                : null,
            ]);
    }
}