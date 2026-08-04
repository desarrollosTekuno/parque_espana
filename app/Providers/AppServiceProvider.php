<?php

namespace App\Providers;

use App\Models\Billing\Payment;
use App\Models\Memberships\MembershipAccountMember;
use App\Observers\MembershipAccountMemberObserver;
use App\Observers\PaymentObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        MembershipAccountMember::observe(MembershipAccountMemberObserver::class);
        Payment::observe(PaymentObserver::class);
    }
}
