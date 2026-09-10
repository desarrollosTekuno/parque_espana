<?php

namespace App\Jobs;

use App\Mail\DailyAccessCardMail;
use App\Models\Administrator\Club;
use App\Services\Email\MailService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendDailyAccessCardMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  int  $clubId
     * @param  string  $email
     * @param  Carbon  $validFrom
     * @param  Carbon  $validUntil
     * @param  array<int, string>  $cardCodes
     */
    public function __construct(
        public int $clubId,
        public string $email,
        public Carbon $validFrom,
        public Carbon $validUntil,
        public array $cardCodes,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MailService $mailService): void
    {
        try {
            $mailService->send(
                entityId: $this->clubId,
                to: $this->email,
                mailable: new DailyAccessCardMail(
                    club: Club::findOrFail($this->clubId),
                    validFrom: $this->validFrom,
                    validUntil: $this->validUntil,
                    cardCodes: $this->cardCodes,
                )
            );
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de acceso del pase diario.', [
                'club_id' => $this->clubId,
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
