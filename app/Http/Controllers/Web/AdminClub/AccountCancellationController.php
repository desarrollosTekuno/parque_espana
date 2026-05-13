<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Catalogs\DocumentType;
use App\Models\Members\LockerAssignment;
use App\Models\Members\MemberDocument;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccountMember;
use App\Services\Billing\MembershipChargeService;
use App\Services\Billing\MembershipPricingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Permission\PermissionRegistrar;

class AccountCancellationController extends Controller
{
    public function __construct(
        protected MembershipChargeService $membershipChargeService,
        protected MembershipPricingService $membershipPricingService
    ) {
        $this->middleware('permission:members.cancel');
    }

    public function create(Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        if ($membership->status !== 'active' || !$membership->is_primary) {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'Solo se puede dar de baja una membresía activa y principal.',
                'exception' => '',
            ]);
        }

        $membership->load([
            'account.primaryHolder.member',
            'account.accountMembers.member',
            'account.accountMembers.relationship',
            'membershipType',
            'club',
        ]);

        $primaryHolder = $membership->account?->primaryHolder?->member;

        $members = $membership->account?->accountMembers->map(function (MembershipAccountMember $accountMember) {
            $member = $accountMember->member;
            return [
                'member_id' => $accountMember->member_id,
                'full_name' => trim(collect([
                    $member?->first_name,
                    $member?->last_name,
                    $member?->second_last_name,
                ])->filter()->implode(' ')),
                'relationship_name' => $accountMember->relationship?->name,
                'is_primary_holder' => (bool) $accountMember->is_primary_holder,
                'email' => $member?->email,
                'has_mobile_access' => $member?->user_id !== null,
            ];
        })->values();

        return Inertia::render('Members/Cancel', [
            'membership' => [
                'id' => $membership->id,
                'membership_number' => $membership->account?->membership_number,
                'holder_name' => trim(collect([
                    $primaryHolder?->first_name,
                    $primaryHolder?->last_name,
                    $primaryHolder?->second_last_name,
                ])->filter()->implode(' ')),
                'membership_type_name' => $membership->membershipType?->name,
                'club_name' => $membership->club?->name,
                'status' => $membership->status,
            ],
            'members' => $members,
        ]);
    }

    public function store(Request $request, Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        if ($membership->status !== 'active' || !$membership->is_primary) {
            return back()->withErrors([
                'messageError' => 'Solo se puede dar de baja una membresía activa y principal.',
                'exception' => '',
            ]);
        }

        $request->validate([
            'cancellation_letter' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'cancellation_letter.required' => 'La carta de baja firmada es obligatoria para continuar.',
            'cancellation_letter.mimes' => 'La carta debe ser un archivo PDF, JPG o PNG.',
            'cancellation_letter.max' => 'La carta no debe superar los 5 MB.',
        ]);

        $membership->load([
            'account.primaryHolder.member',
            'account.accountMembers.member.user',
            'account.memberships',
            'club',
        ]);

        $account = $membership->account;
        $primaryHolder = $account?->primaryHolder?->member;

        if (!$primaryHolder) {
            return back()->withErrors([
                'messageError' => 'No se encontró el titular de la cuenta.',
                'exception' => '',
            ]);
        }

        $cartaBajaType = DocumentType::where('code', 'carta_baja')->first();

        DB::beginTransaction();
        try {
            $now = now();

            $file = $request->file('cancellation_letter');
            $docTypeSlug = $cartaBajaType ? Str::slug($cartaBajaType->name) : 'carta-baja';
            $directory = "members/{$primaryHolder->id}/{$docTypeSlug}";
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

            $uploaded = Storage::disk('spaces')->putFileAs($directory, $file, $filename);

            if ($uploaded === false) {
                throw new \RuntimeException('No se pudo subir la carta de baja a almacenamiento.');
            }

            $letterPath = "{$directory}/{$filename}";

            MemberDocument::create([
                'member_id'        => $primaryHolder->id,
                'document_type_id' => $cartaBajaType?->id,
                'file_path'        => $letterPath,
            ]);

            $account->update([
                'status' => 'cancelled',
                'cancelled_at' => $now,
                'cancelled_by' => auth()->id(),
                'cancellation_letter_path' => $letterPath,
                'cancellation_type' => 'voluntary',
            ]);

            $account->memberships()->update([
                'status' => 'cancelled',
                'end_date' => $now->toDateString(),
            ]);

            foreach ($account->accountMembers as $accountMember) {
                $member = $accountMember->member;

                if (!$member) {
                    continue;
                }

                $lockerAssignments = LockerAssignment::query()
                    ->with('locker')
                    ->where('member_id', $member->id)
                    ->get();

                foreach ($lockerAssignments as $assignment) {
                    $assignment->locker?->update(['status' => 'disponible']);
                    $assignment->delete();
                }

                if ($member->user_id && $member->user) {
                    $user = $member->user;
                    $member->update(['user_id' => null]);
                    $user->tokens()->delete();
                    $user->roles()->detach();
                    $user->clubs()->detach();
                    $user->delete();
                }
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            // Recalculate fees for any active membership in the same account group
            // so they revert from the interclub/split rate to their standalone price.
            $this->membershipPricingService->recalculateGroupFeesAfterCancellation(
                $account,
                $this->membershipChargeService
            );

            DB::commit();

            return redirect()->route('members.index')->with('success', 'La cuenta ha sido dada de baja correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al procesar baja de cuenta', [
                'membership_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors([
                'messageError' => 'Ocurrió un error al procesar la baja. Intente de nuevo.',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
