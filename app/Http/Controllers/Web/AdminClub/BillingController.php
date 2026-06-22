<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Members\Locker;
use App\Models\AdminClub\BusinessAd;
use App\Models\Billing\PaymentMethod;
use App\Models\Memberships\Membership;
use App\Models\Members\LockerAssignment;
use App\Models\Memberships\MembershipAccount;
use App\Jobs\SendPushNotificationJob;
use App\Models\AdminClub\PhysicalAd;
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
        protected PaymentRegistrationService $paymentRegistrationService,
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
                        ->orWhere('internal_account_number', $like, "%{$search}%")
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
                        'internal_account_number' => $account->internal_account_number,
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

            return Inertia::render('Billing/Index', [
                'accounts' => $accounts,
                'summary' => $summary,
                'clubOptions' => $clubOptions,
                'clubPaymentMethods' => $this->resolveClubPaymentMethods(),
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

            $account = MembershipAccount::query()
                ->with('primaryHolder.member')
                ->findOrFail($validated['membership_account_id']);
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
            
            // Notificación push al titular de la cuenta (asíncrona vía queue)
            $userId = $account->primaryHolder?->member?->user_id;
            if ($userId) {
                SendPushNotificationJob::dispatch(
                    $userId,
                    'Pago registrado',
                    sprintf('Se registró un pago de $%s en tu cuenta.', number_format((float) $payment->amount, 2)),
                    // ['screen' => 'AccountStatement', 'type' => 'payment_registered', 'club_id' => (string) $validated['club_id']],
                    ['screen' => 'AccountStatement', 'type' => 'account_statement', 'club_id' => (string) $validated['club_id']],
                );
            }

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

                     // Actualizar monto pagado
                     $assignment = LockerAssignment::where('locker_id', $lockerId)
                        ->where('member_id', $memberId)
                        ->where('year', now()->year)
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$assignment) {
                        return;
                    }
                    $assignment->increment('amount_paid', $amount);

                    /* Validar que siga reservado
                    if ($locker->status !== 'pago_pendiente') {
                        return;
                    }
                    // Evitar duplicados
                    $alreadyAssigned = LockerAssignment::where('member_id', $memberId) 
                        ->where('year', now()->year)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($alreadyAssigned) {
                        return;
                    }

                    $lockerAlreadyAssigned = LockerAssignment::where('locker_id', $lockerId)
                        ->where('year', now()->year)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($lockerAlreadyAssigned) {
                        return;
                    }*/

                    // Crear asignación
                   /* LockerAssignment::create([
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
                    ]);*/
                });
            }

            // Anuncios físicos pagados
            $physicalAdCharges = $charges->filter(function ($charge) {
                return isset($charge->metadata['physical_ad_id']);
            });
            foreach ($physicalAdCharges as $charge) {
                $physicalAdId = $charge->metadata['physical_ad_id'];

                DB::transaction(function () use ($physicalAdId) {
                    $ad = PhysicalAd::lockForUpdate()->find($physicalAdId);
                    if (!$ad || $ad->status_id !== 'paid') {
                        return;
                    }

                    $ad->update([
                        'status' => 'active',
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

    public function chargesList(Request $request)
    {
        try {
            $prefix = 'charges';
            $driver = DB::getDriverName();
            $search = $request->input("{$prefix}_search");
            $clubId = $request->input("{$prefix}_club_id");
            $conceptCode = $request->input("{$prefix}_concept_code");
            $sessionClubId = session('club_id');

            $chargeQuery = Charge::query()
                ->with([
                    'concept',
                    'membership.club',
                    'membership.membershipType',
                    'membershipAccount.primaryHolder.member',
                ])
                ->whereIn('status', ['pending', 'partial'])
                ->when(
                    $clubId,
                    fn (Builder $q) => $q->whereHas('membership', fn (Builder $mq) => $mq->where('club_id', $clubId))
                )
                ->when(
                    $conceptCode,
                    fn (Builder $q) => $q->whereHas('concept', fn (Builder $cq) => $cq->where('code', $conceptCode))
                )
                ->when($search, function (Builder $q) use ($search, $driver) {
                    $like = $driver === 'pgsql' ? 'ilike' : 'like';
                    $concatFn = $driver === 'pgsql'
                        ? "CONCAT(first_name, ' ', last_name, ' ', COALESCE(second_last_name, ''))"
                        : "CONCAT(first_name, ' ', last_name, ' ', IFNULL(second_last_name, ''))";

                    $q->where(function (Builder $inner) use ($search, $like, $concatFn) {
                        $inner->whereHas('membershipAccount', function (Builder $aq) use ($search, $like, $concatFn) {
                            $aq->where('membership_number', $like, "%{$search}%")
                                ->orWhere('internal_account_number', $like, "%{$search}%")
                                ->orWhereHas('primaryHolder.member', function (Builder $mq) use ($search, $like, $concatFn) {
                                    $mq->where('first_name', $like, "%{$search}%")
                                        ->orWhere('last_name', $like, "%{$search}%")
                                        ->orWhere('second_last_name', $like, "%{$search}%")
                                        ->orWhere('email', $like, "%{$search}%")
                                        ->orWhereRaw("{$concatFn} {$like} ?", ["%{$search}%"]);
                                });
                        })->orWhereHas('concept', fn (Builder $cq) => $cq->where('name', $like, "%{$search}%"));
                    });
                });

            $summaryBase = clone $chargeQuery;
            $summary = [
                'total_outstanding' => (float) (clone $summaryBase)->sum('balance'),
                'overdue_outstanding' => (float) (clone $summaryBase)
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now()->toDateString())
                    ->sum('balance'),
                'monthly_outstanding' => (float) (clone $summaryBase)
                    ->whereHas('concept', fn (Builder $q) => $q->where('code', 'MONTHLY_FEE'))
                    ->sum('balance'),
                'inscription_outstanding' => (float) (clone $summaryBase)
                    ->whereHas('concept', fn (Builder $q) => $q->where('code', 'INSCRIPTION'))
                    ->sum('balance'),
                'accounts_with_balance' => (clone $summaryBase)->distinct('membership_account_id')->count('membership_account_id'),
            ];

            $charges = $chargeQuery
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date')
                ->orderBy('id')
                ->paginate(
                    $request->input("{$prefix}_per_page", 15),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(function (Charge $charge) {
                    $account = $charge->membershipAccount;
                    $holder = $account?->primaryHolder?->member;
                    $fullName = trim(collect([
                        $holder?->first_name,
                        $holder?->last_name,
                        $holder?->second_last_name,
                    ])->filter()->implode(' '));

                    return array_merge(
                        [
                            'membership_number' => $account?->membership_number,
                            'internal_account_number' => $account?->internal_account_number,
                            'holder_name' => $fullName ?: '—',
                            'membership_account_id' => $account?->id,
                        ],
                        $this->buildChargePayload($charge),
                    );
                })
                ->appends($request->all());

            $clubOptions = Club::query()
                ->select('id', 'code', 'name')
                ->orderBy('name')
                ->get();

            $conceptOptions = ChargeConcept::query()
                ->select('id', 'code', 'name')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            return Inertia::render('Billing/ChargesList', [
                'charges' => $charges,
                'summary' => $summary,
                'clubOptions' => $clubOptions,
                'conceptOptions' => $conceptOptions,
                'clubPaymentMethods' => $this->resolveClubPaymentMethods(),
                'filters' => [
                    'search' => $search,
                    'club_id' => $clubId ? (int) $clubId : null,
                    'concept_code' => $conceptCode,
                ],
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('Billing/ChargesList', [
                'charges' => ['data' => [], 'total' => 0],
                'summary' => [
                    'total_outstanding' => 0,
                    'overdue_outstanding' => 0,
                    'monthly_outstanding' => 0,
                    'inscription_outstanding' => 0,
                    'accounts_with_balance' => 0,
                ],
                'clubOptions' => [],
                'conceptOptions' => [],
                'clubPaymentMethods' => [],
                'filters' => [
                    'search' => $request->input('charges_search'),
                    'club_id' => $request->input('charges_club_id'),
                    'concept_code' => $request->input('charges_concept_code'),
                ],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function accountCharges(Membership $membership)
    {
        $account = $membership->account()->with([
            'charges' => fn ($q) => $q
                ->with(['concept', 'membership.club'])
                ->whereIn('status', ['pending', 'partial'])
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date')
                ->orderBy('id'),
        ])->firstOrFail();

        $charges = $account->charges->map(fn (Charge $charge) => $this->buildChargePayload($charge))->values();

        return response()->json([
            'charges' => $charges,
            'outstanding_balance' => (float) $account->charges->sum('balance'),
            'club_payment_methods' => $this->resolveClubPaymentMethods(),
        ]);
    }

    protected function resolveClubPaymentMethods(): \Illuminate\Support\Collection
    {
        $canUseNonCashCut = auth()->user()?->can('billing.payments.non-cash-cut') ?? false;

        return Club::query()
            ->with([
                'clubPaymentMethods' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('display_order'),
                'clubPaymentMethods.paymentMethod',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Club $club) use ($canUseNonCashCut) {
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
                                'show_in_billing' => (bool) $clubPaymentMethod->paymentMethod?->show_in_billing,
                                'internal_key' => $clubPaymentMethod->internal_key,
                            ];
                        })
                        ->filter(function (array $method) use ($canUseNonCashCut) {
                            if (empty($method['id'])) return false;
                            if (!$method['show_in_billing']) return false;
                            if (!$method['affects_cash_cut'] && !$canUseNonCashCut) return false;
                            return true;
                        })
                        ->values(),
                ];
            })
            ->values();
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
