<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Billing\Charge;
use App\Models\Members\Locker;
use App\Models\AdminClub\BusinessAd;
use App\Models\Billing\PaymentMethod;
use App\Models\Members\LockerAssignment;
use App\Models\Memberships\MembershipAccount;
use App\Rules\ExistsInSchema;
use App\Services\Billing\PaymentRegistrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function __construct(
        protected PaymentRegistrationService $paymentRegistrationService
    ) {
    }

    public function index(Request $request)
    {
        try {
            $prefix = 'billing';
            $driver = DB::getDriverName();
            $search = $request->input("{$prefix}_search");
            $clubId = $request->input("{$prefix}_club_id");
            $sessionClubId = session('club_id');

            $accountQuery = MembershipAccount::query()
                ->with([
                    'primaryHolder.member',
                    'memberships' => fn ($membershipQuery) => $membershipQuery
                        ->with(['club', 'membershipType'])
                        ->where('status', 'active')
                        ->where('is_primary', true),
                    'charges' => fn ($chargeQuery) => $chargeQuery
                        ->with(['concept', 'membership.club'])
                        ->whereIn('status', ['pending', 'partial'])
                        ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                        ->orderBy('due_date')
                        ->orderBy('id'),
                ])
                ->whereHas('primaryHolder.member')
                ->whereHas('charges', function (Builder $chargeQuery) use ($clubId) {
                    $chargeQuery->whereIn('status', ['pending', 'partial']);

                    if ($clubId) {
                        $chargeQuery->whereHas('membership', function (Builder $membershipQuery) use ($clubId) {
                            $membershipQuery->where('club_id', $clubId);
                        });
                    }
                });

            if ($search) {
                $like = $driver === 'pgsql' ? 'ilike' : 'like';

                $accountQuery->where(function (Builder $query) use ($search, $like) {
                    $query->where('membership_number', $like, "%{$search}%")
                        ->orWhereHas('primaryHolder.member', function (Builder $memberQuery) use ($search, $like) {
                            $memberQuery->where('first_name', $like, "%{$search}%")
                                ->orWhere('last_name', $like, "%{$search}%")
                                ->orWhere('second_last_name', $like, "%{$search}%")
                                ->orWhere('email', $like, "%{$search}%")
                                ->orWhere('phone', $like, "%{$search}%");
                        })
                        ->orWhereHas('charges.concept', function (Builder $conceptQuery) use ($search, $like) {
                            $conceptQuery->where('name', $like, "%{$search}%");
                        });
                });
            }

            $summaryBaseQuery = Charge::query()
                ->whereIn('status', ['pending', 'partial'])
                ->when(
                    $clubId,
                    fn (Builder $query) => $query->whereHas('membership', function (Builder $membershipQuery) use ($clubId) {
                        $membershipQuery->where('club_id', $clubId);
                    })
                )
                ->when(
                    $search,
                    function (Builder $query) use ($search, $driver) {
                        $like = $driver === 'pgsql' ? 'ilike' : 'like';

                        $query->where(function (Builder $chargeQuery) use ($search, $like) {
                            $chargeQuery->where('description', $like, "%{$search}%")
                                ->orWhereHas('concept', function (Builder $conceptQuery) use ($search, $like) {
                                    $conceptQuery->where('name', $like, "%{$search}%");
                                })
                                ->orWhereHas('membershipAccount', function (Builder $accountQuery) use ($search, $like) {
                                    $accountQuery->where('membership_number', $like, "%{$search}%")
                                        ->orWhereHas('primaryHolder.member', function (Builder $memberQuery) use ($search, $like) {
                                            $memberQuery->where('first_name', $like, "%{$search}%")
                                                ->orWhere('last_name', $like, "%{$search}%")
                                                ->orWhere('second_last_name', $like, "%{$search}%");
                                        });
                                });
                        });
                    }
                );

            $summary = [
                'total_outstanding' => (float) (clone $summaryBaseQuery)->sum('balance'),
                'overdue_outstanding' => (float) (clone $summaryBaseQuery)
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now()->toDateString())
                    ->sum('balance'),
                'monthly_outstanding' => (float) (clone $summaryBaseQuery)
                    ->whereHas('concept', fn (Builder $query) => $query->where('code', 'MONTHLY_FEE'))
                    ->sum('balance'),
                'inscription_outstanding' => (float) (clone $summaryBaseQuery)
                    ->whereHas('concept', fn (Builder $query) => $query->where('code', 'INSCRIPTION'))
                    ->sum('balance'),
                'accounts_with_balance' => (clone $accountQuery)->count(),
            ];

            $accounts = $accountQuery
                ->orderByDesc('id')
                ->paginate(
                    $request->input("{$prefix}_per_page", 10),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(function (MembershipAccount $account) use ($clubId, $sessionClubId) {
                    $charges = $account->charges
                        ->filter(function (Charge $charge) use ($clubId) {
                            if (!$clubId) {
                                return true;
                            }

                            return (int) ($charge->membership?->club_id ?? 0) === (int) $clubId;
                        })
                        ->values();

                    $holder = $account->primaryHolder?->member;
                    $fullName = trim(collect([
                        $holder?->first_name,
                        $holder?->last_name,
                        $holder?->second_last_name,
                    ])->filter()->implode(' '));
                    $activeMemberships = $account->memberships->values();
                    $sessionMembership = $activeMemberships
                        ->firstWhere('club_id', $sessionClubId);
                    $primaryMembershipId = $sessionMembership?->id;
                    $nextDueCharge = $charges
                        ->sortBy(fn (Charge $charge) => $charge->due_date ?: '9999-12-31')
                        ->first();

                    return [
                        'id' => $account->id,
                        'membership_number' => $account->membership_number,
                        'holder_name' => $fullName,
                        'email' => $holder?->email,
                        'phone' => $holder?->phone,
                        'primary_membership_id' => $primaryMembershipId,
                        'has_session_membership' => $sessionMembership !== null,
                        'session_membership_club_name' => $sessionMembership?->club?->name,
                        'session_membership_club_code' => $sessionMembership?->club?->code,
                        'outstanding_balance' => (float) $charges->sum('balance'),
                        'pending_charges_count' => $charges->count(),
                        'next_due_date' => $nextDueCharge?->due_date,
                        'clubs' => $charges
                            ->map(function (Charge $charge) {
                                $club = $charge->membership?->club;

                                if (!$club) {
                                    return null;
                                }

                                return [
                                    'id' => $club->id,
                                    'code' => $club->code,
                                    'name' => $club->name,
                                ];
                            })
                            ->filter()
                            ->unique('id')
                            ->values(),
                        'charge_summary' => $charges
                            ->groupBy(fn (Charge $charge) => $charge->concept?->name ?? 'Otro')
                            ->map(function ($group, $conceptName) {
                                return [
                                    'concept_name' => $conceptName,
                                    'count' => $group->count(),
                                    'balance' => (float) $group->sum('balance'),
                                ];
                            })
                            ->values(),
                        'charges' => $charges
                            ->map(fn (Charge $charge) => $this->buildChargePayload($charge))
                            ->values(),
                    ];
                })
                ->appends($request->all());

            $clubOptions = Club::query()
                ->select('id', 'code', 'name')
                ->orderBy('name')
                ->get();

            $clubPaymentMethods = Club::query()
                ->with([
                    'clubPaymentMethods' => fn ($query) => $query
                        ->where('is_active', true)
                        ->orderBy('display_order'),
                    'clubPaymentMethods.paymentMethod',
                ])
                ->orderBy('name')
                ->get()
                ->map(function (Club $club) {
                    return [
                        'id' => $club->id,
                        'code' => $club->code,
                        'name' => $club->name,
                        'payment_methods' => $club->clubPaymentMethods
                            ->map(function ($clubPaymentMethod) {
                                return [
                                    'id' => $clubPaymentMethod->paymentMethod?->id,
                                    'code' => $clubPaymentMethod->paymentMethod?->code,
                                    'name' => $clubPaymentMethod->paymentMethod?->name,
                                    'requires_reference' => (bool) $clubPaymentMethod->paymentMethod?->requires_reference,
                                    'requires_bank_name' => (bool) $clubPaymentMethod->paymentMethod?->requires_bank_name,
                                    'requires_check_number' => (bool) $clubPaymentMethod->paymentMethod?->requires_check_number,
                                    'affects_cash_cut' => (bool) $clubPaymentMethod->paymentMethod?->affects_cash_cut,
                                ];
                            })
                            ->filter(fn (array $method) => !empty($method['id']))
                            ->values(),
                    ];
                })
                ->values();

            return Inertia::render('Billing/Index', [
                'accounts' => $accounts,
                'summary' => $summary,
                'clubOptions' => $clubOptions,
                'clubPaymentMethods' => $clubPaymentMethods,
                'filters' => [
                    'search' => $search,
                    'club_id' => $clubId ? (int) $clubId : null,
                ],
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('Billing/Index', [
                'accounts' => [
                    'data' => [],
                    'total' => 0,
                ],
                'summary' => [
                    'total_outstanding' => 0,
                    'overdue_outstanding' => 0,
                    'monthly_outstanding' => 0,
                    'inscription_outstanding' => 0,
                    'accounts_with_balance' => 0,
                ],
                'clubOptions' => [],
                'clubPaymentMethods' => [],
                'filters' => [
                    'search' => $request->input('billing_search'),
                    'club_id' => $request->input('billing_club_id'),
                ],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function storePayment(Request $request) 
    {
        try {
            $validated = $request->validate([
                'membership_account_id' => ['required', new ExistsInSchema('memberships', 'accounts', 'id')],
                'club_id' => ['required', new ExistsInSchema('clubs', 'clubs', 'id')],
                'payment_method_id' => ['required', new ExistsInSchema('billing', 'payment_methods', 'id')],
                'paid_at' => ['required', 'date'],
                'reference' => ['nullable', 'string', 'max:255'],
                'bank_name' => ['nullable', 'string', 'max:255'],
                'check_number' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'applications' => ['required', 'array', 'min:1'],
                'applications.*.charge_id' => ['required', new ExistsInSchema('billing', 'charges', 'id')],
                'applications.*.amount' => ['required', 'numeric', 'gt:0'],
            ]);

            $account = MembershipAccount::query()->findOrFail($validated['membership_account_id']);
            $paymentMethod = PaymentMethod::query()
                ->where('id', $validated['payment_method_id'])
                ->where('is_active', true)
                ->firstOrFail();

            $payment = $this->paymentRegistrationService->register(
                account: $account,
                clubId: (int) $validated['club_id'],
                paymentMethod: $paymentMethod,
                applications: $validated['applications'],
                paidAt: $validated['paid_at'],
                reference: $validated['reference'] ?? null,
                bankName: $validated['bank_name'] ?? null,
                checkNumber: $validated['check_number'] ?? null,
                notes: $validated['notes'] ?? null,
                receivedBy: $request->user()?->id,
                sessionClubId: session('club_id')
            );
            
            // Actualizar estatus de anuncios relacionados a los cargos aplicados
            $chargeIds = collect($validated['applications'])->pluck('charge_id');
            $charges = Charge::whereIn('id', $chargeIds)->get();
            $businessAdIds = $charges
                ->pluck('metadata.business_ad_id')
                ->filter()
                ->unique();

            BusinessAd::whereIn('id', $businessAdIds)
                ->where('status_id', 3)
                ->update([
                    'status_id' => 5,
                    'paid_at' => now(),
                    'published_at' => now(),
                    'expires_at' => now()->addMonth()
                ]);
            
            // Procesar casilleros pagados
            $lockerCharges = $charges->filter(function ($charge) {
                return isset($charge->metadata['locker_id']);
            });

            foreach ($lockerCharges as $charge) {

                $lockerId = $charge->metadata['locker_id'];
                $memberId = $charge->member_id;
                $amount = $charge->amount;

                DB::transaction(function () use ($lockerId, $memberId, $amount) {
                    $locker = Locker::lockForUpdate()->find($lockerId);
                    if (!$locker) {
                        return;
                    }
                    // Validar que siga reservado
                    if ($locker->status !== 'pago_pendiente') {
                        return;
                    }
                    // Evitar duplicados
                    $alreadyAssigned = LockerAssignment::where('member_id', $memberId)
                        ->where('year', now()->year)
                        ->exists();

                    if ($alreadyAssigned) {
                        return;
                    }

                    // Crear asignación
                    LockerAssignment::create([
                        'locker_id' => $locker->id,
                        'member_id' => $memberId,
                        'amount_paid' => $amount,
                        'start_date' => now(),
                        'end_date' => now()->endOfYear(),
                        'year' => now()->year,
                    ]);

                    // Actualizar locker
                    $locker->update([
                        'status' => 'ocupado',
                    ]);
                });
            }

            return redirect()->back()->with('success', sprintf(
                'Cobro registrado correctamente por $%s.',
                number_format((float) $payment->amount, 2)
            ));
        } catch (ValidationException $e) {
            $errors = $e->errors();

            return redirect()->back()->withErrors(array_merge($errors, [
                'messageError' => collect($errors)->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al registrar el cobro.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function buildChargePayload(Charge $charge): array
    {
        $club = $charge->membership?->club;
        $metadata = is_array($charge->metadata) ? $charge->metadata : [];
        $originCode = $this->resolveChargeOriginCode($charge, $metadata);
        $badges = $this->resolveChargeBadges($metadata);

        return [
            'id' => $charge->id,
            'concept_name' => $charge->concept?->name,
            'concept_code' => $charge->concept?->code,
            'description' => $charge->description,
            'amount' => (float) $charge->amount,
            'balance' => (float) $charge->balance,
            'due_date' => $charge->due_date,
            'status' => $charge->status,
            'allows_partial_payments' => (bool) $charge->allows_partial_payments,
            'club_id' => $club?->id,
            'club_code' => $club?->code,
            'club_name' => $club?->name,
            'membership_type_name' => $charge->membership?->membershipType?->name,
            'origin_code' => $originCode,
            'origin_label' => $this->resolveChargeOriginLabel($originCode),
            'badges' => $badges,
            'target_monthly_fee' => isset($metadata['target_monthly_fee']) ? (float) $metadata['target_monthly_fee'] : null,
            'monthly_fee_total' => isset($metadata['monthly_fee_total']) ? (float) $metadata['monthly_fee_total'] : null,
            'monthly_fee_share' => isset($metadata['monthly_fee_share']) ? (float) $metadata['monthly_fee_share'] : null,
            'effective_monthly_fee' => isset($metadata['effective_monthly_fee']) ? (float) $metadata['effective_monthly_fee'] : null,
        ];
    }

    protected function resolveChargeOriginCode(Charge $charge, array $metadata): string
    {
        if (($charge->concept?->code ?? null) === 'INSCRIPTION') {
            return 'inscription';
        }

        if (!empty($metadata['is_monthly_adjustment'])) {
            return 'monthly_adjustment';
        }

        if (($metadata['generation_type'] ?? null) === 'monthly_cycle') {
            return 'monthly_cycle';
        }

        return match ($metadata['charge_origin'] ?? null) {
            'membership_registration' => 'membership_registration',
            'additional_membership' => 'additional_membership',
            'membership_transition' => 'membership_transition',
            'age_transition' => 'age_transition',
            default => 'charge',
        };
    }

    protected function resolveChargeOriginLabel(string $originCode): string
    {
        return match ($originCode) {
            'inscription' => 'Inscripción',
            'monthly_cycle' => 'Mensualidad del período',
            'monthly_adjustment' => 'Ajuste mensual',
            'membership_registration' => 'Alta de membresía',
            'additional_membership' => 'Membresía adicional',
            'membership_transition' => 'Cambio de membresía',
            'age_transition' => 'Transición por edad',
            default => 'Cargo generado',
        };
    }

    protected function resolveChargeBadges(array $metadata): array
    {
        $badges = [];

        if (!empty($metadata['split_mode'])) {
            $badges[] = [
                'label' => '50/50',
                'color' => 'primary',
            ];
        }

        if (!empty($metadata['absence_permit_id'])) {
            $badges[] = [
                'label' => 'Permiso por ausencia',
                'color' => 'warning',
            ];
        }

        if (($metadata['generation_type'] ?? null) === 'monthly_cycle') {
            $badges[] = [
                'label' => 'Mensualidad',
                'color' => 'secondary',
            ];
        }

        if (!empty($metadata['is_monthly_adjustment'])) {
            $badges[] = [
                'label' => 'Ajuste',
                'color' => 'info',
            ];
        }

        return $badges;
    }
}
