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

        // Documentos como la carta de recomendación o el formato de solicitud
        // son propios de un solo parque (ver members.documents.club_id):
        // no se pueden ver desde otro club aunque el socio comparta cuenta ahí.
        if ($document->club_id !== null && (int) $document->club_id !== (int) $clubId) {
            abort(403);
        }

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
