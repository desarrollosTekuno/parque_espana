<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Members\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemberDocumentController extends Controller
{
    /**
     * GET /api/v1/my-documents
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $member = Member::where('user_id', $user->id)->first();

        if (!$member) {
            return $this->notFound('No se encontró un perfil de socio asociado a este usuario.');
        }

        $memberIds = $member->accounts()
            ->with('members')
            ->get()
            ->flatMap(fn ($account) => $account->members->pluck('id'))
            ->unique()
            ->values();

        $members = Member::whereIn('id', $memberIds)
            ->with([
                'documents.documentType',
                'accountMemberships' => fn ($q) => $q->where('is_primary_holder', true),
            ])
            ->get();

        $expiresAt = now()->addMinutes(30);

        $data = $members->map(function (Member $m) use ($member, $expiresAt) {
            $isPrimaryHolder = $m->accountMemberships->isNotEmpty();

            $documents = $m->documents->map(function ($doc) use ($expiresAt) {
                $url = null;

                try {
                    $url = Storage::disk('spaces')->temporaryUrl($doc->file_path, $expiresAt);
                } catch (\Throwable) {
                    // archivo no disponible
                }

                return [
                    'id'               => $doc->id,
                    'document_type_id' => $doc->document_type_id,
                    'document_type'    => $doc->documentType?->name ?? '',
                    'is_verified'      => $doc->is_verified,
                    'verified_at'      => $doc->verified_at,
                    'uploaded_at'      => $doc->created_at,
                    'url'              => $url,
                    'url_expires_at'   => $url ? $expiresAt->toIso8601String() : null,
                ];
            })->values();

            return [
                'member_id'         => $m->id,
                'full_name'         => $m->full_name,
                'is_primary_holder' => $isPrimaryHolder,
                'is_self'           => $m->id === $member->id,
                'documents'         => $documents,
            ];
        });

        return $this->ok($data);
    }
}
