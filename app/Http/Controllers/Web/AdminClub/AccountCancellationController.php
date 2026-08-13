<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Billing\Charge;
use App\Models\Catalogs\DocumentType;
use App\Models\Members\LockerAssignment;
use App\Models\Members\MemberDocument;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountMember;
use App\Services\Billing\MembershipChargeService;
use App\Services\Billing\MembershipPricingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\PermissionRegistrar;

class AccountCancellationController extends Controller
{
    public function __construct(
        protected MembershipChargeService $membershipChargeService,
        protected MembershipPricingService $membershipPricingService
    ) {
        $this->middleware('permission:members.cancel.create');
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

        $validated = $request->validate([
            'cancellation_letter' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'waive_pending_charges' => ['sometimes', 'boolean'],
        ], [
            'cancellation_letter.required' => 'La carta de baja firmada es obligatoria para continuar.',
            'cancellation_letter.mimes' => 'La carta debe ser un archivo PDF, JPG o PNG.',
            'cancellation_letter.max' => 'La carta no debe superar los 5 MB.',
        ]);
        $waivePendingCharges = (bool) ($validated['waive_pending_charges'] ?? false);

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

            $activeMemberships = $account->memberships()->where('status', 'active')->get();

            $account->memberships()->update([
                'status' => 'cancelled',
                'end_date' => $now->toDateString(),
            ]);

            foreach ($activeMemberships as $m) {
                DB::table('memberships.membership_history')->insert([
                    'membership_id'          => $m->id,
                    'old_membership_type_id' => $m->membership_type_id,
                    'new_membership_type_id' => $m->membership_type_id,
                    'changed_by'             => auth()->id(),
                    'effective_date'         => $now->toDateString(),
                    'reason'                 => 'Baja voluntaria de cuenta',
                    'previous_monthly_fee'   => (float) $m->monthly_fee,
                    'new_monthly_fee'        => null,
                    'metadata'               => json_encode(['cancellation_type' => 'voluntary']),
                    'created_at'             => $now,
                    'updated_at'             => $now,
                ]);
            }

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

            // Condonar adeudo pendiente: solo si el encargado lo marcó
            // explícitamente Y esta baja deja al socio sin NINGUNA membresía
            // activa en ningún parque (si todavía tiene una en otro parque,
            // ese adeudo sigue siendo cobrable ahí, así que se rechaza toda
            // la baja para que el encargado decida qué hacer primero).
            if ($waivePendingCharges) {
                if ($this->accountGroupHasRemainingActiveMemberships($account)) {
                    throw ValidationException::withMessages([
                        'waive_pending_charges' => 'No se puede condonar el adeudo: el socio todavía tiene una membresía activa en otro parque.',
                    ]);
                }

                $this->waivePendingCharges($account, $now);
            }

            DB::commit();

            return redirect()->route('members.index')->with('success', 'La cuenta ha sido dada de baja correctamente.');
        } catch (ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors());
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

    /**
     * True si, después de esta baja, el socio se queda sin ninguna
     * membresía activa/suspendida en ningún parque — el mismo criterio que
     * ya usa MembershipPricingService::recalculateGroupFeesAfterCancellation
     * para saber si el grupo interclub sigue vivo en algún otro parque.
     */
    protected function accountGroupHasRemainingActiveMemberships(MembershipAccount $account): bool
    {
        $query = Membership::query()
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended']);

        if ($account->account_group_id) {
            $query->whereHas('account', fn ($q) => $q->where('account_group_id', $account->account_group_id));
        } else {
            $query->where('membership_account_id', $account->id);
        }

        return $query->exists();
    }

    /**
     * Condona (cancela) todos los cargos pendientes/parciales de TODAS las
     * cuentas del grupo del socio (todos sus parques) — no solo mensualidad,
     * cualquier concepto. Se deja mapeado en el propio cargo (cancelled_at,
     * cancelled_by, cancellation_reason) en vez de solo desaparecer de los
     * pendientes: billing.charges.status ya tenía 'cancelled' en su enum, y
     * ya se excluye en todos los reportes/cobros existentes que filtran por
     * status pendiente/parcial — no hacía falta un estado nuevo.
     */
    protected function waivePendingCharges(MembershipAccount $account, \Illuminate\Support\Carbon $now): void
    {
        $groupAccountIds = $account->account_group_id
            ? MembershipAccount::query()->where('account_group_id', $account->account_group_id)->pluck('id')->all()
            : [$account->id];

        Charge::query()
            ->whereIn('membership_account_id', $groupAccountIds)
            ->whereIn('status', ['pending', 'partial'])
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => $now,
                'cancelled_by' => auth()->id(),
                'cancellation_reason' => 'Condonado por baja de cuenta',
                'updated_at' => $now,
            ]);

        // Además de cancelar lo que ya existía como cargo, marca a partir de
        // cuándo puede volver a rellenarse mensualidad faltante (ver
        // MembershipChargeService::ensureMonthlyChargesUpToToday) — sin esto,
        // al reactivar la cuenta el backfill automático volvía a generar
        // mensualidad de meses previos a la baja que nunca se habían
        // facturado, como si el adeudo condonado no hubiera aplicado a ellos.
        MembershipAccount::query()
            ->whereIn('id', $groupAccountIds)
            ->update(['billing_backfill_floor' => $now->toDateString()]);
    }
}
