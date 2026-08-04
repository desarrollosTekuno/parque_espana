<?php

namespace App\Observers;

use App\Models\Billing\Payment;
use App\Services\Billing\FolioService;

class PaymentObserver
{
    public function __construct(private FolioService $folioService)
    {
    }

    public function creating(Payment $payment): void
    {
        if (!$payment->folio) {
            $payment->folio = $this->folioService->generate($payment);
        }
    }
}
