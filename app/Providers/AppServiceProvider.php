<?php

namespace App\Providers;

use App\Models\Memberships\MembershipAccountMember;
use App\Observers\MembershipAccountMemberObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {
        //
    }

    public function boot(): void {
        MembershipAccountMember::observe(MembershipAccountMemberObserver::class);
    }
}
