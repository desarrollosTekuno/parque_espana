<?php

namespace Tests\Unit;

use App\Models\Billing\Payment;
use App\Observers\PaymentObserver;
use App\Services\Billing\FolioService;
use PHPUnit\Framework\TestCase;

class PaymentObserverTest extends TestCase
{
    public function test_it_assigns_a_folio_when_the_payment_does_not_have_one(): void
    {
        $folioService = $this->createMock(FolioService::class);
        $folioService->expects($this->once())
            ->method('generate')
            ->willReturn('PE1-CAJ-260804-001');

        $payment = new Payment();
        $observer = new PaymentObserver($folioService);

        $observer->creating($payment);

        $this->assertSame('PE1-CAJ-260804-001', $payment->folio);
    }

    public function test_it_keeps_an_existing_folio(): void
    {
        $folioService = $this->createMock(FolioService::class);
        $folioService->expects($this->never())
            ->method('generate');

        $payment = new Payment();
        $payment->folio = 'FOLIO-EXISTENTE';
        $observer = new PaymentObserver($folioService);

        $observer->creating($payment);

        $this->assertSame('FOLIO-EXISTENTE', $payment->folio);
    }
}
