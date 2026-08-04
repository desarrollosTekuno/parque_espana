<?php

namespace App\Services\Billing;

use App\Models\Administrator\Club;
use App\Models\Billing\FolioSequence;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentMethod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FolioService
{
    public function generate(Payment $payment): string
    {
        $club = Club::findOrFail($payment->club_id);
        $paidAt = Carbon::parse($payment->paid_at ?? now());
        $userId = null;
        $series = 'AUTO';

        if ($payment->received_by) {
            $user = User::find($payment->received_by);

            if ($user) {
                $userId = $user->id;
                $series = $user->code ?: 'USR' . $user->id;
            }
        } else {
            $paymentMethod = PaymentMethod::find($payment->payment_method_id);

            if ($paymentMethod && strtoupper($paymentMethod->code) === 'SPEI') {
                $series = 'SPEI';
            }
        }

        $clubCode = $this->normalizeCode($club->code ?: 'CLUB' . $club->id);
        $series = $this->normalizeCode($series);

        return DB::transaction(function () use ($club, $clubCode, $userId, $series, $paidAt) {
            DB::table('billing.folio_sequences')->insertOrIgnore([
                'club_id' => $club->id,
                'user_id' => $userId,
                'series' => $series,
                'sequence_date' => $paidAt->toDateString(),
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = FolioSequence::query()
                ->where('club_id', $club->id)
                ->where('series', $series)
                ->whereDate('sequence_date', $paidAt->toDateString())
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->last_number = $sequence->last_number + 1;
            $sequence->save();

            $number = str_pad((string) $sequence->last_number, 3, '0', STR_PAD_LEFT);

            return $clubCode . '-' . $series . '-' . $paidAt->format('ymd') . '-' . $number;
        });
    }

    private function normalizeCode(string $code): string
    {
        $normalized = strtoupper(Str::ascii($code));
        $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized) ?? '';

        if ($normalized === '') {
            return 'AUTO';
        }

        return substr($normalized, 0, 20);
    }
}
