<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Members\Member;
use App\Models\Members\LockerAssignmentHistory;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class LockerAssignmentHistoryController extends Controller {

public function __construct()
    {
        $this->middleware('permission:members.lockers.history')->only('history');
    }

    public function index(Member $member) 
    {
        $history = LockerAssignmentHistory::with([
                'member:id,first_name,last_name',
                'oldLocker:id,number',
                'newLocker:id,number',
            ])
            ->where('member_id', $member->id)
            ->latest()
            ->paginate(10);

        $history->getCollection()->transform(function ($item) {
            //dd(Storage::disk('spaces')->url($item->file_path));
            return [
                'id' => $item->id,
                'created_at' => $item->created_at->toISOString(), 
                'old_locker' => $item->oldLocker,
                'new_locker' => $item->newLocker,
                'member_name' => trim(
                                ($item->member->first_name ?? '') . ' ' .
                                ($item->member->last_name ?? '')
                            ),
                'file_url' => $item->file_path
                            ? Storage::disk('spaces')->temporaryUrl(
                                $item->file_path,
                                now()->addMinutes(30)
                            )
                            : null,
            ];
        });

        return response()->json($history);
    }

    public function MyFunction(Request $request) {
        return "MyFunction";
    }
}
