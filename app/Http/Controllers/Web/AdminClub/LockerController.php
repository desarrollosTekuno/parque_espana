<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use App\Models\Members\LockerAssignment;
use App\Models\Members\Locker;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LockerController extends Controller {

    public function available(Request $request)
    {   
        return Locker::query()
            ->where('club_id', $request->club_id)
            ->when($request->category, fn ($q) =>
                $q->where('category', $request->category)
            )
            ->where('status', 'disponible')
            ->orderBy('number')
            ->get();
    }

    public function assignedByAccount(Request $request)
    {
        return LockerAssignment::with(['locker', 'member'])
            ->whereHas('member', function ($q) use ($request) {
                $q->whereIn('id', function ($sub) use ($request) {
                    $sub->select('member_id')
                        ->from('memberships.account_members')
                        ->where('membership_account_id', $request->account_id);
                });
            })
            ->where('year', now()->year)
            ->get();
    }
}
