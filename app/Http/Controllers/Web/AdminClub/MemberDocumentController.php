<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Members\MemberDocument;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class MemberDocumentController extends Controller
{
    public function temporaryUrl(MemberDocument $document)
    {
        $clubId = session('club_id');

        $belongsToClub = $document->member?->accounts()
            ->whereHas('memberships', fn ($q) => $q->where('club_id', $clubId))
            ->exists();

        if (!$belongsToClub) {
            abort(403);
        }

        $url = Storage::disk('spaces')->temporaryUrl(
            $document->file_path,
            now()->addMinutes(10)
        );

        return response()->json(['url' => $url]);
    }
}
