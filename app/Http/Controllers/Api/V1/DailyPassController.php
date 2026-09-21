<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendDailyAccessCardMail;
use App\Models\AdminClub\SystemVariable;
use App\Models\Administrator\Club;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Devices\ScheduledDailyPass;
use App\Models\Members\Member;
use App\Models\Memberships\Membership;
use App\Services\Access\GuestPassProvisioningService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class DailyPassController extends Controller {

    public function __construct(
        private GuestPassProvisioningService $guestPassProvisioningService
    ) { }

    public function store(Request $request, Club $club) {
        try {

            // Reutiliza la misma variable que Reservaciones, por decisión de negocio.
            $maxDaysAhead = (int) SystemVariable::where('club_id', $club->id)
                ->where('name', 'dias_para_crear_reserva')
                ->value('value');

            $today = Carbon::today();
            $maxDate = $today->copy()->addDays($maxDaysAhead);

            $validated = $request->validate([
                'date' => ['required', 'date_format:d-m-Y'],
                'email' => ['required', 'email', 'max:200'],
                'visitors' => ['required', 'array', 'min:1'],
                'visitors.*.first_name' => ['required', 'string', 'max:150'],
                'visitors.*.last_name' => ['required', 'string', 'max:150'],
                'visitors.*.phone' => ['nullable', 'string', 'max:30']
            ]);

            $visitDate = Carbon::createFromFormat('d-m-Y', $validated['date'])->startOfDay();

            if ($visitDate->lt($today) || $visitDate->gt($maxDate)) {
                return $this->unprocessable(
                    "La fecha debe estar entre hoy y los próximos {$maxDaysAhead} día(s)."
                );
            }

            $member = Member::where('user_id', $request->user()->id)->first();

            if (!$member) {
                return $this->unprocessable('No se encontró el registro de socio para este usuario.');
            }

            $member->load('accountMemberships.membershipAccount');
            $accountMember = $member->accountMemberships
                ->first(fn ($am) => (int) $am->membershipAccount?->club_id === (int) $club->id);

            if (!$accountMember) {
                return $this->unprocessable('El socio no tiene una cuenta de membresía en este club.');
            }

            $concept = ChargeConcept::where('code', '20')->first();

            if (!$concept) {
                return $this->unprocessable('No existe el concepto de pase diario (código 20) en el catálogo.');
            }

            $unitAmount = $concept->resolveAmountForClub($club->id);

            if ($unitAmount === null) {
                return $this->unprocessable('El concepto de pase diario no tiene un monto configurado para este club.');
            }

            $membership = Membership::query()
                ->where('membership_account_id', $accountMember->membership_account_id)
                ->where('club_id', $club->id)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
                ->first();

            $quantity = count($validated['visitors']);
            $totalAmount = round($unitAmount * $quantity, 2);
            $isToday = $visitDate->isSameDay($today);

            $result = DB::transaction(function () use (
                $club, $accountMember, $member, $concept, $totalAmount, $quantity,
                $visitDate, $validated, $isToday, $unitAmount, $membership
            ) {
                $charge = Charge::create([
                    'membership_account_id' => $accountMember->membership_account_id,
                    'membership_id' => $membership->id,
                    'member_id' => $member->id,
                    'concept_id' => $concept->id,
                    'description' => $concept->name,
                    'amount' => $totalAmount,
                    'balance' => $totalAmount,
                    'issue_date' => now()->toDateString(),
                    'due_date' => now()->toDateString(),
                    'allows_partial_payments' => false,
                    'status' => 'pending',
                    'metadata' => [
                        'charge_origin' => 'mobile_app_daily_pass',
                        'quantity' => $quantity,
                        'unit_amount' => $unitAmount,
                    ],
                ]);

                $scheduledDailyPass = ScheduledDailyPass::create([
                    'club_id' => $club->id,
                    'account_member_id' => $accountMember->id,
                    'charge_id' => $charge->id,
                    'visit_date' => $visitDate->toDateString(),
                    'notify_email' => $validated['email'],
                    'status' => 'pending',
                ]);

                foreach ($validated['visitors'] as $visitor) {
                    $scheduledDailyPass->visitors()->create([
                        'first_name' => $visitor['first_name'],
                        'last_name' => $visitor['last_name'],
                        'phone' => $visitor['phone'] ?? null,
                    ]);
                }

                $cardCodes = [];

                if ($isToday) {
                    for ($i = 0; $i < $quantity; $i++) {
                        $cardCodes[] = $this->guestPassProvisioningService->provisionDayPass(
                            clubId: $club->id,
                            validUntil: now()->addDay(),
                            accountMemberId: $accountMember->id,
                            chargeId: $charge->id,
                        );
                    }
                } else {
                    $visitDateEnd = $visitDate->copy()->endOfDay();

                    for ($i = 0; $i < $quantity; $i++) {
                        $cardCodes[] = $this->guestPassProvisioningService->scheduleCard(
                            clubId: $club->id,
                            validUntil: $visitDateEnd,
                            accountMemberId: $accountMember->id,
                            chargeId: $charge->id,
                        );
                    }
                }

                $scheduledDailyPass->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);

                return [
                    'scheduledDailyPass' => $scheduledDailyPass,
                    'cardCodes' => $cardCodes,
                ];
            });

            SendDailyAccessCardMail::dispatch(
                clubId: $club->id,
                email: $validated['email'],
                cardCodes: $result['cardCodes'],
            );

            return $this->created('Pase diario registrado correctamente.', [
                'id' => $result['scheduledDailyPass']->id,
                'visit_date' => $result['scheduledDailyPass']->visit_date->toDateString(),
                'status' => $result['scheduledDailyPass']->status,
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
            ]);

        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Ocurrió un error al crear los pases diarios.');
        }
    }

    public function MyFunction(Request $request) {
        return "MyFunction";
    }
}
