<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Exports\ChargeReportExport;
use App\Exports\IncomeReportExport;
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
use App\Models\Billing\AnnualDiscountRule;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\Payment;
use App\Models\Members\MemberDocument;
use App\Rules\ExistsInSchema;
use App\Services\Billing\AnnualPaymentService;
use App\Services\Billing\MembershipChargeService;
use App\Services\Billing\PaymentRegistrationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class BillingController extends Controller {
    public function __construct(
        protected PaymentRegistrationService $paymentRegistrationService,
    ) {
    }

    public function exportChargesReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $tz = config('app.timezone');
        $clubId = (int) session('club_id');
        $club = $clubId ? Club::find($clubId) : null;

        $start = Carbon::parse($validated['start_date'], $tz)->startOfDay()->utc();
        $end = Carbon::parse($validated['end_date'], $tz)->endOfDay()->utc();

        $filename = 'reporte-cobranza-' . $validated['start_date'] . '-a-' . $validated['end_date'] . '.xlsx';

        return Excel::download(
            new ChargeReportExport($start, $end, $clubId ?: null, $club?->name),
            $filename,
        );
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
                    'primaryHolder.member.documents' => fn ($documentQuery) => $documentQuery
                        ->whereHas('documentType', fn (Builder $documentTypeQuery) => $documentTypeQuery
                            ->where('code', 'fotografia_infantil')),
                    'primaryHolder.member.documents.documentType',
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
                })
                ->when(
                    !$clubId && $sessionClubId,
                    // Sin filtro explícito de parque: solo cuentas con membresía en el
                    // parque activo, o cuentas hermanas del mismo account_group_id que
                    // tengan membresía ahí (grupos repartidos entre parques).
                    fn (Builder $query) => $query->where(function (Builder $sub) use ($sessionClubId) {
                        $sub->whereHas('memberships', fn (Builder $mq) => $mq->where('club_id', $sessionClubId))
                            ->orWhereHas(
                                'accountGroup.accounts.memberships',
                                fn (Builder $mq) => $mq->where('club_id', $sessionClubId)
                            );
                    })
                );

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
                    !$clubId && $sessionClubId,
                    fn (Builder $query) => $query->whereHas('membershipAccount', function (Builder $aq) use ($sessionClubId) {
                        $aq->whereHas('memberships', fn (Builder $mq) => $mq->where('club_id', $sessionClubId))
                            ->orWhereHas(
                                'accountGroup.accounts.memberships',
                                fn (Builder $mq) => $mq->where('club_id', $sessionClubId)
                            );
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
                        'photo' => $this->resolveHolderPhotoUrl($holder),
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
                    !$clubId && $sessionClubId,
                    // Sin filtro explícito de parque: muestra los cargos de las cuentas que
                    // tienen alguna membresía en el parque activo, aunque el cargo pertenezca
                    // a la membresía del otro parque (cuentas compartidas entre parques), y
                    // también los de cualquier cuenta hermana del mismo account_group_id
                    // (grupos familiares/relacionados repartidos entre parques).
                    fn (Builder $q) => $q->whereHas('membershipAccount', function (Builder $aq) use ($sessionClubId) {
                        $aq->whereHas('memberships', fn (Builder $mq) => $mq->where('club_id', $sessionClubId))
                            ->orWhereHas(
                                'accountGroup.accounts.memberships',
                                fn (Builder $mq) => $mq->where('club_id', $sessionClubId)
                            );
                    })
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

            // El resumen se calcula sobre el mismo conjunto de cargos ya filtrado arriba
            // (incluye cuentas compartidas entre parques cuando no hay filtro explícito).
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

    public function annualPaymentPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'membership_account_id' => ['required', 'integer'],
            'club_id'               => ['required', 'integer'],
            'year'                  => ['required', 'integer', 'min:2020', 'max:2035'],
            'paid_at'               => ['required', 'date'],
        ]);

        $account = MembershipAccount::findOrFail($validated['membership_account_id']);
        $year    = (int) $validated['year'];
        $clubId  = (int) $validated['club_id'];

        // Membresía activa del socio en ese parque
        $membership = Membership::where('membership_account_id', $account->id)
            ->where('club_id', $clubId)
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended'])
            ->first();

        if (!$membership) {
            return response()->json([
                'charges_count'   => 0,
                'months_covered'  => [],
                'total_balance'   => 0,
                'discount_rule'   => null,
                'discount_amount' => 0,
                'payment_amount'  => 0,
                'error'           => 'No se encontró una membresía activa para ese parque.',
            ]);
        }

        // Cuota mensual de la membresía (independiente de qué cargos ya existan)
        $monthlyFee = $membership->resolved_monthly_fee_share;

        // Cargos ya existentes para el año, para saber cuáles meses ya tienen cargo
        $existingCharges = Charge::query()
            ->where('membership_account_id', $account->id)
            ->where('period_year', $year)
            ->whereNotIn('status', ['cancelled'])
            ->whereHas('concept', fn ($q) => $q->where('code', 'MONTHLY_FEE'))
            ->whereHas('membership', fn ($q) => $q->where('club_id', $clubId))
            ->get()
            ->keyBy('period_month');

        // 12 meses: usa el balance del cargo existente si ya existe, o la cuota mensual si falta
        $totalBalance = 0.0;
        $monthsCovered = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthsCovered[] = $m;
            $existing = $existingCharges->get($m);
            $totalBalance += $existing ? (float) $existing->balance : $monthlyFee;
        }
        $totalBalance = round($totalBalance, 2);

        $paymentMonth   = Carbon::parse($validated['paid_at'])->month;
        $rule           = AnnualDiscountRule::findApplicable($paymentMonth);
        $discountAmount = $rule ? round($monthlyFee * (float) $rule->discount_months, 2) : 0.0;
        $paymentAmount  = round($totalBalance - $discountAmount, 2);

        return response()->json([
            'charges_count'   => 12,
            'months_covered'  => $monthsCovered,
            'total_balance'   => $totalBalance,
            'monthly_fee'     => $monthlyFee,
            'discount_rule'   => $rule ? [
                'pay_by_month'    => $rule->pay_by_month,
                'discount_months' => $rule->discount_months,
                'free_month'      => $rule->free_month,
            ] : null,
            'discount_amount' => $discountAmount,
            'payment_amount'  => $paymentAmount,
        ]);
    }

    public function storeAnnualPayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'membership_account_id' => ['required', new ExistsInSchema('memberships', 'accounts', 'id')],
                'club_id'               => ['required', new ExistsInSchema('clubs', 'clubs', 'id')],
                'year'                  => ['required', 'integer', 'min:2020', 'max:2035'],
                'payment_method_id'     => ['required', new ExistsInSchema('billing', 'payment_methods', 'id')],
                'paid_at'               => ['required', 'date'],
                'reference'             => ['nullable', 'string', 'max:255'],
                'bank_name'             => ['nullable', 'string', 'max:255'],
                'check_number'          => ['nullable', 'string', 'max:255'],
                'notes'                 => ['nullable', 'string', 'max:1000'],
            ]);

            $account       = MembershipAccount::with('primaryHolder.member')->findOrFail($validated['membership_account_id']);
            $paymentMethod = PaymentMethod::where('id', $validated['payment_method_id'])->where('is_active', true)->firstOrFail();
            $year          = (int) $validated['year'];
            $clubId        = (int) $validated['club_id'];

            $allowed = ClubPaymentMethod::where('club_id', $clubId)
                ->where('payment_method_id', $paymentMethod->id)
                ->where('is_active', true)
                ->exists();

            if (!$allowed) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'El método de pago seleccionado no está habilitado para ese parque.',
                ]);
            }

            // Membresía activa del socio en ese parque
            $membership = Membership::where('membership_account_id', $account->id)
                ->where('club_id', $clubId)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
                ->with(['membershipType', 'account.primaryHolder.member'])
                ->first();

            if (!$membership) {
                throw ValidationException::withMessages([
                    'club_id' => 'No se encontró una membresía activa para ese parque.',
                ]);
            }

            // Generar los cargos mensuales faltantes para todos los meses del año
            $chargeService = app(MembershipChargeService::class);
            for ($month = 1; $month <= 12; $month++) {
                $periodDate = Carbon::create($year, $month, 1);
                $chargeService->createRecurringMonthlyCharge($membership, $periodDate, [
                    'charge_origin' => 'annual_payment',
                ]);
            }

            // Ahora sí cargar todos los cargos pendientes del año
            $charges = Charge::query()
                ->where('membership_account_id', $account->id)
                ->where('period_year', $year)
                ->whereIn('status', ['pending', 'partial'])
                ->whereHas('concept', fn ($q) => $q->where('code', 'MONTHLY_FEE'))
                ->whereHas('membership', fn ($q) => $q->where('club_id', $clubId))
                ->orderBy('period_month')
                ->lockForUpdate()
                ->get();

            if ($charges->isEmpty()) {
                throw ValidationException::withMessages([
                    'year' => "No se encontraron cargos de mensualidad para {$year} en ese parque.",
                ]);
            }

            $monthlyFee     = $membership->resolved_monthly_fee_share;
            $paymentMonth   = Carbon::parse($validated['paid_at'])->month;
            $rule           = AnnualDiscountRule::findApplicable($paymentMonth);
            $discountAmount = $rule ? round($monthlyFee * (float) $rule->discount_months, 2) : 0.0;
            $paymentAmount  = round((float) $charges->sum('balance') - $discountAmount, 2);

            if ($paymentAmount <= 0) {
                throw ValidationException::withMessages([
                    'year' => 'El monto calculado es cero. Verifica que los cargos existan.',
                ]);
            }

            $payment = DB::transaction(function () use (
                $account, $validated, $paymentMethod, $paymentAmount,
                $year, $clubId, $rule, $discountAmount, $request
            ) {
                $payment = Payment::create([
                    'membership_account_id' => $account->id,
                    'club_id'               => $clubId,
                    'payment_method_id'     => $paymentMethod->id,
                    'amount'                => $paymentAmount,
                    'paid_at'               => $validated['paid_at'],
                    'reference'             => $validated['reference'] ?? null,
                    'bank_name'             => $validated['bank_name'] ?? null,
                    'check_number'          => $validated['check_number'] ?? null,
                    'notes'                 => $validated['notes'] ?? null,
                    'received_by'           => $request->user()?->id,
                    'status'                => 'registered',
                    'metadata'              => [
                        'payment_type'       => 'annual',
                        'year'               => $year,
                        'session_club_id'    => session('club_id'),
                        'discount_rule_id'   => $rule?->id,
                        'discount_months'    => $rule?->discount_months,
                        'discount_amount'    => $discountAmount,
                        'affects_cash_cut'   => (bool) $paymentMethod->affects_cash_cut,
                        'settlement_channel' => $paymentMethod->affects_cash_cut ? 'cashier' : 'services',
                    ],
                ]);

                app(AnnualPaymentService::class)->processAnnualPayment($account, [$account->id], $year, collect([$payment]), $rule);

                return $payment;
            });

            $userId = $account->primaryHolder?->member?->user_id;
            if ($userId) {
                SendPushNotificationJob::dispatch(
                    $userId,
                    'Pago de anualidad registrado',
                    sprintf('Se registró tu pago de anualidad %s por $%s.', $year, number_format($paymentAmount, 2)),
                    ['screen' => 'AccountStatement', 'type' => 'account_statement', 'club_id' => (string) $clubId],
                );
            }

            return redirect()->back()->with('success', sprintf(
                'Pago de anualidad %s registrado correctamente por $%s.',
                $year,
                number_format($paymentAmount, 2)
            ));
        } catch (ValidationException $e) {
            $errors = $e->errors();

            return redirect()->back()->withErrors(array_merge($errors, [
                'annual_messageError' => collect($errors)->flatten()->first() ?? 'Error de validación.',
                'annual_exception'    => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'annual_messageError' => 'Ocurrió un error al registrar el pago de anualidad.',
                'annual_exception'    => $e->getMessage(),
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

    protected function resolveHolderPhotoUrl(?\App\Models\Members\Member $holder): ?string
    {
        $photoDocument = $holder?->documents
            ->first(fn (MemberDocument $document) => $document->documentType?->code === 'fotografia_infantil');

        if (!$photoDocument) {
            return null;
        }

        return Storage::disk('spaces')->temporaryUrl(
            $photoDocument->file_path,
            now()->addMinutes(30)
        );
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

    protected function resolveChargeOriginCode(Charge $charge, array $metadata): string {
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

    protected function resolveChargeOriginLabel(string $originCode): string {
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

    protected function resolveChargeBadges(array $metadata): array {
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

    public function incomeReport(Request $request) {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $timezone = config('app.timezone');
        $fechaInicio = Carbon::parse($validated['start_date'], $timezone);
        $fechaFin = Carbon::parse($validated['end_date'], $timezone);
        $clubId = (int) session('club_id');
        $club = Club::find($clubId);
        $clubName = $club?->name ?? 'Parque España II';
        $logoContent = null;
        $logoUrl = $this->logoUrl($club?->code, $club?->logo_url);

        if ($logoUrl && str_starts_with($logoUrl, '/')) {
            $logoPath = public_path(ltrim($logoUrl, '/'));
            $logoContent = file_exists($logoPath) ? file_get_contents($logoPath) : null;
        } elseif ($club?->logo_path) {
            try {
                $logoContent = Storage::disk('spaces')->get($club->logo_path);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $payments = Payment::query()
            ->with('paymentMethod')
            ->where('club_id', $clubId)
            ->where('status', 'registered')
            ->whereBetween('paid_at', [
                $fechaInicio->copy()->startOfDay()->utc(),
                $fechaFin->copy()->endOfDay()->utc(),
            ])
            ->get();

        $deliveredBy = $request->user()?->name ?? '';
        $filename = 'reporte-ingresos-'.$fechaInicio->format('Y-m-d').'-a-'.$fechaFin->format('Y-m-d').'-'.now()->format('H-i-s').'.xlsx';

        return Excel::download(new IncomeReportExport($clubName, $fechaInicio, $fechaFin, $payments, $deliveredBy, $logoContent),$filename);
    }

    private function logoUrl(?string $clubCode, ?string $configuredLogo): ?string {
        return match (strtoupper((string) $clubCode)) {
            'PE1' => '/assets/images/LogoP1.png',
            'PE2' => '/assets/images/LogoP2.png',
            default => $configuredLogo,
        };
    }
}
