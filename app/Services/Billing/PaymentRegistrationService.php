<?php

namespace App\Services\Billing;

use App\Models\Billing\Charge;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use App\Models\Billing\PaymentMethod;
use App\Models\Memberships\MembershipAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentRegistrationService
{
    public function register(
        ?MembershipAccount $account,
        int $clubId,
        PaymentMethod $paymentMethod,
        array $applications,
        string $paidAt,
        ?string $reference,
        ?string $bankName,
        ?string $checkNumber,
        ?string $notes,
        ?int $receivedBy,
        ?int $sessionClubId = null,
        array $accountClubIds = [],
        array $groupAccountIds = []
    ): Payment {
        $charges = $this->resolveCharges($groupAccountIds ?: ($account ? [$account->id] : []), $applications);

        $this->ensureChargesBelongToClub($charges, $clubId, $accountClubIds);
        $this->ensurePaymentMethodAllowedForClub($paymentMethod->id, $clubId);
        $this->validateMethodRequirements($paymentMethod, $reference, $bankName, $checkNumber);
        $normalizedApplications = $this->normalizeApplications($charges, $applications);
        $parkSplit = $this->resolveParkSplit($normalizedApplications);
        ['subtotal' => $subtotal, 'iva' => $iva] = $this->calculateSubtotalAndIva($normalizedApplications, $clubId);

        return DB::transaction(function () use (
            $account,
            $clubId,
            $paymentMethod,
            $normalizedApplications,
            $paidAt,
            $reference,
            $bankName,
            $checkNumber,
            $notes,
            $receivedBy,
            $sessionClubId,
            $parkSplit,
            $subtotal,
            $iva
        ) {
            $totalAmount = round($normalizedApplications->sum('amount'), 2);

            $payment = Payment::create([
                'payment_group_id' => (string) Str::uuid(),
                'membership_account_id' => $account?->id,
                'club_id' => $clubId,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $totalAmount,
                'subtotal' => $subtotal,
                'iva' => $iva,
                'paid_at' => $paidAt,
                'reference' => $reference,
                'bank_name' => $bankName,
                'check_number' => $checkNumber,
                'notes' => $notes,
                'received_by' => $receivedBy,
                'status' => 'registered',
                'metadata' => [
                    'session_club_id' => $sessionClubId,
                    'settlement_channel' => $paymentMethod->affects_cash_cut ? 'cashier' : 'services',
                    'affects_cash_cut' => (bool) $paymentMethod->affects_cash_cut,
                    'park_split' => $parkSplit,
                ],
            ]);

            $normalizedApplications->each(function (array $application) use ($payment, $clubId) {
                $charge = $application['charge'];
                $amount = $application['amount'];
                $discount = (float) ($application['discount'] ?? 0);
                $newBalance = round((float) $charge->balance - $amount - $discount, 2);
                $breakdown = $this->resolveApplicationSubtotalAndIva($application, $clubId);

                PaymentApplication::create([
                    'payment_id' => $payment->id,
                    'charge_id' => $charge->id,
                    'applied_amount' => $amount,
                    'discount' => $discount > 0 ? $discount : null,
                    'subtotal' => $breakdown['subtotal'],
                    'iva' => $breakdown['iva'],
                ]);

                $charge->update([
                    'balance' => $newBalance,
                    'status' => $newBalance <= 0 ? 'paid' : 'partial',
                ]);
            });

            return $payment;
        });
    }

    /**
     * Igual que register(), pero permite cobrar con más de una forma de pago
     * a la vez (p. ej. una parte en efectivo y otra con tarjeta). $payments
     * es un arreglo de líneas: [['payment_method_id' => int, 'amount' =>
     * float, 'reference' => ?string, 'bank_name' => ?string, 'check_number'
     * => ?string], ...]. La suma de $payments debe coincidir exactamente con
     * la suma de $applications. Cada cargo se reparte entre las formas de
     * pago en el orden en que llegan (waterfall), así que un mismo cargo
     * puede terminar cubierto por dos pagos distintos si hace falta. Regresa
     * la colección de Payment creados (uno por línea).
     */
    public function registerSplit(
        ?MembershipAccount $account,
        int $clubId,
        array $applications,
        array $payments,
        string $paidAt,
        ?string $notes,
        ?int $receivedBy,
        ?int $sessionClubId = null,
        array $accountClubIds = [],
        array $groupAccountIds = []
    ): Collection {
        if (empty($payments)) {
            throw ValidationException::withMessages([
                'payments' => 'Agrega al menos una forma de pago.',
            ]);
        }

        $charges = $this->resolveCharges($groupAccountIds ?: ($account ? [$account->id] : []), $applications);
        $this->ensureChargesBelongToClub($charges, $clubId, $accountClubIds);
        $normalizedApplications = $this->normalizeApplications($charges, $applications);

        // Si alguno de los cargos que se están pagando es de un concepto que
        // se reparte entre parques (billing.concepts.splits_between_parks,
        // p. ej. MONTHLY_FEE) y el socio tiene membresía en más de un parque,
        // se permite pagar con un método de pago configurado en CUALQUIERA
        // de esos parques, no solo el de la sesión — p. ej. la tarjeta de
        // crédito propia del otro parque — aunque el pago se siga
        // registrando completo en el parque de la sesión (es informativo,
        // no crea un segundo pago en el otro parque). No se puede usar
        // resolveParkSplit aquí: el cargo real de la mensualidad siempre
        // vive en UN solo parque (el de la membresía facturable), el
        // reparto 50/50 es solo informativo a nivel de concepto.
        $allowCrossClubMethods = count($accountClubIds) > 1
            && $normalizedApplications->contains(
                fn (array $application) => (bool) ($application['charge']->concept?->splits_between_parks)
            );

        $paymentMethodCache = [];
        $paymentLines = collect($payments)->values()->map(function (array $line) use ($clubId, $accountClubIds, $allowCrossClubMethods, &$paymentMethodCache) {
            $methodId = (int) $line['payment_method_id'];
            $paymentMethod = $paymentMethodCache[$methodId] ??= PaymentMethod::findOrFail($methodId);
            $amount = round((float) ($line['amount'] ?? 0), 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'payments' => 'El importe de cada forma de pago debe ser mayor a cero.',
                ]);
            }

            $this->ensurePaymentMethodAllowedForClub($paymentMethod->id, $clubId, $accountClubIds, $allowCrossClubMethods);
            $this->validateMethodRequirements(
                $paymentMethod,
                $line['reference'] ?? null,
                $line['bank_name'] ?? null,
                $line['check_number'] ?? null
            );

            return [
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'reference' => $line['reference'] ?? null,
                'bank_name' => $line['bank_name'] ?? null,
                'check_number' => $line['check_number'] ?? null,
                // Ver PaymentLinePayload en el frontend: true para la fila
                // del parque de la sesión con pareja, sus "otro pago" extra,
                // y la fila espejo del otro parque — se usa abajo para que
                // esas líneas se apliquen primero al cargo que se reparte
                // entre parques, antes de mezclarse con cualquier otro cargo.
                'is_park_split' => (bool) ($line['is_park_split'] ?? false),
                // A qué parque representa esta línea específica (puede ser
                // distinto al parque donde se registra el dinero — ver
                // represents_club_id abajo en Payment::create).
                'represents_club_id' => isset($line['club_id']) ? (int) $line['club_id'] : null,
            ];
        });

        $totalApplications = round($normalizedApplications->sum('amount'), 2);
        $totalPayments = round($paymentLines->sum('amount'), 2);

        if (abs($totalApplications - $totalPayments) > 0.01) {
            throw ValidationException::withMessages([
                'payments' => 'La suma de las formas de pago debe coincidir con el total a cobrar.',
            ]);
        }

        // Reparte cada cargo entre las formas de pago en el orden dado, para
        // saber cuánto de cada cargo cubrió cada forma de pago. Antes de
        // repartir, se reordena (de forma estable, sin alterar el orden
        // relativo dentro de cada grupo):
        //   - los cargos que se reparten entre parques (splits_between_parks,
        //     p. ej. la mensualidad combo) van primero;
        //   - las líneas de pago marcadas is_park_split van primero.
        // Sin esto, una línea "pareja" (p. ej. Tarjeta de crédito del otro
        // parque, cuyo monto es exactamente la mitad de la mensualidad) podía
        // terminar cubriendo sobre todo OTRO cargo si antes ya se habían
        // consumido otras formas de pago sobre la mensualidad — el total por
        // cargo seguía cuadrando, pero el reparto por parque (informativo)
        // de esa línea quedaba incorrecto. Priorizar ambos lados hace que,
        // en el caso normal, las líneas pareja se consuman EXACTO contra el
        // cargo que reparten, sin sobrante que se vaya a otro lado.
        $orderedApplications = $normalizedApplications
            ->sortByDesc(fn (array $application) => (bool) ($application['charge']->concept?->splits_between_parks))
            ->values();

        $paymentLineIndexes = $paymentLines->keys()
            ->sortByDesc(fn ($idx) => (bool) $paymentLines[$idx]['is_park_split'])
            ->values();

        $remainingPerLine = $paymentLines->map(fn (array $line) => $line['amount'])->all();
        $allocationsPerLine = array_fill(0, $paymentLines->count(), []);

        foreach ($orderedApplications as $application) {
            $remainingForCharge = $application['amount'];
            $discountForCharge = (float) ($application['discount'] ?? 0);
            $lastTouchedIdx = null;

            foreach ($paymentLineIndexes as $idx) {
                if ($remainingForCharge <= 0.001) {
                    break;
                }
                if ($remainingPerLine[$idx] <= 0.001) {
                    continue;
                }

                $take = round(min($remainingForCharge, $remainingPerLine[$idx]), 2);
                $allocationsPerLine[$idx][] = ['charge' => $application['charge'], 'amount' => $take, 'discount' => 0.0];
                $lastTouchedIdx = $idx;
                $remainingPerLine[$idx] = round($remainingPerLine[$idx] - $take, 2);
                $remainingForCharge = round($remainingForCharge - $take, 2);
            }

            if ($discountForCharge > 0.001) {
                // El descuento no consume dinero de ninguna forma de pago —
                // se adjunta al último renglón que sí cubrió este cargo con
                // dinero real (para no crear un renglón de más), o si el
                // cargo se cubre COMPLETO con descuento (mes libre), a un
                // renglón nuevo sin dinero en la primera forma de pago —
                // así siempre queda un billing.payment_applications que el
                // ticket pueda mostrar (ver PaymentTicketService::concepts).
                if ($lastTouchedIdx !== null) {
                    $lastIndex = array_key_last($allocationsPerLine[$lastTouchedIdx]);
                    $allocationsPerLine[$lastTouchedIdx][$lastIndex]['discount'] += $discountForCharge;
                } else {
                    $targetIdx = $paymentLineIndexes->first();
                    $allocationsPerLine[$targetIdx][] = ['charge' => $application['charge'], 'amount' => 0.0, 'discount' => $discountForCharge];
                }
            }
        }

        return DB::transaction(function () use (
            $account,
            $clubId,
            $paymentLines,
            $allocationsPerLine,
            $paidAt,
            $notes,
            $receivedBy,
            $sessionClubId
        ) {
            $createdPayments = collect();
            // Comparten este id todas las líneas creadas en esta misma
            // llamada (un mismo cobro dividido en varias formas de pago) —
            // ver PaymentTicketService::data, que arma el desglose completo
            // del cobro para el ticket de cualquiera de ellas a partir de
            // este campo.
            $paymentGroupId = (string) Str::uuid();

            foreach ($paymentLines as $idx => $line) {
                $lineAllocations = collect($allocationsPerLine[$idx]);
                $parkSplit = $this->resolveParkSplit($lineAllocations);
                // Subtotal/IVA se calculan sobre lo que ESTA línea cubrió
                // (lineAllocations), no sobre el total del pago completo:
                // cada forma de pago puede cubrir cargos distintos.
                ['subtotal' => $lineSubtotal, 'iva' => $lineIva] = $this->calculateSubtotalAndIva($lineAllocations, $clubId);
                $paymentMethod = $line['payment_method'];

                $payment = Payment::create([
                    'payment_group_id' => $paymentGroupId,
                    'membership_account_id' => $account?->id,
                    'club_id' => $clubId,
                    'payment_method_id' => $paymentMethod->id,
                    'amount' => $line['amount'],
                    'subtotal' => $lineSubtotal,
                    'iva' => $lineIva,
                    'paid_at' => $paidAt,
                    'reference' => $line['reference'],
                    'bank_name' => $line['bank_name'],
                    'check_number' => $line['check_number'],
                    'notes' => $notes,
                    'received_by' => $receivedBy,
                    'status' => 'registered',
                    'metadata' => [
                        'session_club_id' => $sessionClubId,
                        'settlement_channel' => $paymentMethod->affects_cash_cut ? 'cashier' : 'services',
                        'affects_cash_cut' => (bool) $paymentMethod->affects_cash_cut,
                        'park_split' => $parkSplit,
                        'split_payment' => $paymentLines->count() > 1,
                        // Parque que representa ESTA línea específica (p. ej.
                        // "Cheque (PE1)" cobrado desde una sesión en PE2) —
                        // puede ser distinto a club_id (dónde se registra el
                        // dinero). Null cuando la línea no es de un parque
                        // específico (p. ej. Efectivo) o no vino del diálogo
                        // de método de pago con parques (compatibilidad).
                        // Ver PaymentCancellationService::resolveBouncedCheckConceptCode.
                        'represents_club_id' => $line['represents_club_id'],
                    ],
                ]);

                $lineAllocations->each(function (array $allocation) use ($payment, $clubId) {
                    $charge = $allocation['charge'];
                    $amount = $allocation['amount'];
                    $discount = (float) ($allocation['discount'] ?? 0);
                    $newBalance = round((float) $charge->balance - $amount - $discount, 2);
                    $breakdown = $this->resolveApplicationSubtotalAndIva($allocation, $clubId);

                    PaymentApplication::create([
                        'payment_id' => $payment->id,
                        'charge_id' => $charge->id,
                        'applied_amount' => $amount,
                        'discount' => $discount > 0 ? $discount : null,
                        'subtotal' => $breakdown['subtotal'],
                        'iva' => $breakdown['iva'],
                    ]);

                    $charge->update([
                        'balance' => $newBalance,
                        'status' => $newBalance <= 0 ? 'paid' : 'partial',
                    ]);
                });

                $createdPayments->push($payment);
            }

            return $createdPayments;
        });
    }

    /**
     * $accountIds: la cuenta que cobra y, si el socio pertenece a más de un
     * parque, las demás cuentas de su mismo account_group_id (ver
     * CollectionController::resolveGroupAccountIds). Solo los cargos de
     * MONTHLY_FEE pueden venir de una cuenta distinta a la que cobra (ver
     * ensureChargesBelongToClub). Los de un concepto marcado
     * requires_account=false (p. ej. CAFETERIA_PASS, o una venta "sin
     * cuenta" desde Collections/Index.vue) no tienen cuenta — son de un
     * visitante anónimo — así que se incluyen aparte por id.
     */
    protected function resolveCharges(array $accountIds, array $applications): Collection
    {
        $chargeIds = collect($applications)
            ->pluck('charge_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $charges = Charge::query()
            ->with(['membership.club', 'concept'])
            ->whereIn('id', $chargeIds)
            ->where(function (Builder $scope) use ($accountIds) {
                $scope->whereIn('membership_account_id', $accountIds)
                    ->orWhere(function (Builder $anonymous) {
                        $anonymous->whereNull('membership_account_id')
                            ->whereHas('concept', fn (Builder $c) => $c->where('requires_account', false));
                    });
            })
            ->whereIn('status', ['pending', 'partial'])
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($charges->count() !== $chargeIds->count()) {
            throw ValidationException::withMessages([
                'applications' => 'Uno o mas cargos seleccionados ya no estan disponibles para cobro.',
            ]);
        }

        return $charges;
    }

    /**
     * Todos los cargos deben pertenecer al parque que está cobrando, salvo:
     * los de mensualidad (MONTHLY_FEE), que pueden venir de cualquier otro
     * parque donde el socio tenga membresía (ver $accountClubIds, que el
     * controlador arma a partir de las membresías reales del socio, nunca
     * de un club arbitrario) para poder cobrarse juntos en un solo pago; y
     * los de un concepto requires_account=false (p. ej. CAFETERIA_PASS, o
     * una venta "sin cuenta"), que no tienen membresía/club ligado (son de
     * un visitante anónimo) y siempre se cobran en el parque donde se
     * registró la venta.
     */
    protected function ensureChargesBelongToClub(Collection $charges, int $clubId, array $accountClubIds = []): void
    {
        $invalidCharge = $charges->first(function (Charge $charge) use ($clubId, $accountClubIds) {
            if ($charge->membership_account_id === null && $charge->concept?->requires_account === false) {
                return false;
            }

            $chargeClubId = (int) ($charge->membership?->club_id ?? 0);

            if ($chargeClubId === $clubId) {
                return false;
            }

            $isMonthlySplit = $charge->concept?->code === 'MONTHLY_FEE'
                && in_array($chargeClubId, $accountClubIds, true);

            return !$isMonthlySplit;
        });

        if ($invalidCharge) {
            throw ValidationException::withMessages([
                'club_id' => 'Todos los cargos seleccionados deben pertenecer al mismo parque.',
            ]);
        }
    }

    /**
     * Desglose informativo Subtotal/IVA del monto que cubre este pago
     * (amount), cargo por cargo: cada cargo aporta su parte según si el
     * concepto de ESE cargo factura IVA en el parque DONDE SE ESTÁ COBRANDO
     * ($clubId) — no en el parque al que pertenece el cargo. Es el mismo
     * criterio que ya usa la vista de cobranza (resolveConceptAppliesIva con
     * cobroClub, no con el club de cada cargo individual): una mensualidad
     * puede tener un cargo viejo en otro parque (ver el caso de la
     * membresía que dejó de ser facturable), pero el IVA se decide por
     * dónde se está cobrando, para que sea consistente con lo que ve el
     * cajero. Un mismo pago puede cubrir cargos de conceptos distintos,
     * unos con IVA y otros sin. No cambia el monto cobrado (amount), solo
     * cómo se reporta.
     */
    protected function calculateSubtotalAndIva(Collection $applications, int $clubId): array
    {
        $subtotal = 0.0;
        $iva = 0.0;

        foreach ($applications as $application) {
            $breakdown = $this->resolveApplicationSubtotalAndIva($application, $clubId);
            $subtotal += $breakdown['subtotal'];
            $iva += $breakdown['iva'];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'iva' => round($iva, 2),
        ];
    }

    /**
     * Mismo cálculo que calculateSubtotalAndIva pero para UNA sola línea
     * (un cargo cubierto) — se usa tanto para sumar el total del pago como
     * para guardar el desglose en la propia PaymentApplication (ver
     * register()/registerSplit()), porque un mismo cargo puede cubrirse con
     * más de un pago (dividido en varias formas de pago) y hay que poder
     * sumar exactamente lo que le tocó a ESE cargo, no solo al pago completo.
     */
    protected function resolveApplicationSubtotalAndIva(array $application, int $clubId): array
    {
        $charge = $application['charge'];
        // La base del desglose es el valor BRUTO cubierto por este renglón
        // (dinero real + el descuento que le tocó, si trae uno — ver
        // registerSplit) para que el Subtotal/IVA del ticket reflejen el
        // valor completo del cargo, no solo lo que en verdad se cobró.
        $amount = (float) $application['amount'] + (float) ($application['discount'] ?? 0);
        $appliesIva = (bool) $charge->concept?->resolveAppliesIvaForClub($clubId);

        if ($appliesIva) {
            $subtotal = round(($amount * 100) / 116, 2);
            $iva = round(($subtotal * 16) / 100, 2);
        } else {
            $subtotal = round($amount, 2);
            $iva = 0.0;
        }

        return ['subtotal' => $subtotal, 'iva' => $iva];
    }

    /**
     * Desglose del pago por parque cuando los cargos aplicados abarcan más
     * de un club (mensualidad dividida entre Parque España 1 y 2). Null si
     * todos los cargos son del mismo parque.
     */
    protected function resolveParkSplit(Collection $normalizedApplications): ?array
    {
        $byClub = $normalizedApplications->groupBy(
            fn (array $application) => (int) ($application['charge']->membership?->club_id ?? 0)
        );

        if ($byClub->count() < 2) {
            return null;
        }

        return $byClub->map(function (Collection $apps, $clubId) {
            $club = $apps->first()['charge']->membership?->club;

            return [
                'club_id' => $clubId ?: null,
                'club_code' => $club?->code,
                'club_name' => $club?->name,
                // Bruto (dinero real + descuento) para que el reparto por
                // parque siga sumando el valor completo del cargo.
                'amount' => round($apps->sum(fn (array $a) => (float) $a['amount'] + (float) ($a['discount'] ?? 0)), 2),
            ];
        })->values()->all();
    }

    protected function ensurePaymentMethodAllowedForClub(
        int $paymentMethodId,
        int $clubId,
        array $accountClubIds = [],
        bool $allowCrossClub = false
    ): void {
        $isAllowed = ClubPaymentMethod::query()
            ->where('club_id', $clubId)
            ->where('payment_method_id', $paymentMethodId)
            ->where('is_active', true)
            ->exists();

        if ($isAllowed) {
            return;
        }

        if ($allowCrossClub && !empty($accountClubIds)) {
            $isAllowedElsewhere = ClubPaymentMethod::query()
                ->whereIn('club_id', $accountClubIds)
                ->where('payment_method_id', $paymentMethodId)
                ->where('is_active', true)
                ->exists();

            if ($isAllowedElsewhere) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'payment_method_id' => 'El metodo de pago seleccionado no esta habilitado para este parque.',
        ]);
    }

    protected function validateMethodRequirements(
        PaymentMethod $paymentMethod,
        ?string $reference,
        ?string $bankName,
        ?string $checkNumber
    ): void {
        $errors = [];

        if ($paymentMethod->requires_reference && blank($reference)) {
            $errors['reference'] = 'Debes capturar la referencia del pago.';
        }

        if ($paymentMethod->requires_bank_name && blank($bankName)) {
            $errors['bank_name'] = 'Debes capturar el banco del pago.';
        }

        if ($paymentMethod->requires_check_number && blank($checkNumber)) {
            $errors['check_number'] = 'Debes capturar el número de cheque.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function normalizeApplications(Collection $charges, array $applications): Collection
    {
        $normalizedApplications = collect($applications)
            ->map(function (array $application) use ($charges) {
                $charge = $charges->get((int) $application['charge_id']);
                // 'amount' es SOLO la parte que se cobra con dinero real
                // (lo que hay que repartir entre las formas de pago);
                // 'discount' es la parte que se le perdona al cargo sin que
                // salga de ninguna forma de pago (p. ej. mes libre de una
                // anualidad, ver CollectionController::resolveAnnualApplications).
                // Juntas ('coverage') son lo que de verdad libera el saldo
                // del cargo.
                $amount = round((float) ($application['amount'] ?? 0), 2);
                $discount = round((float) ($application['discount'] ?? 0), 2);

                if (!$charge) {
                    return null;
                }

                if ($amount < 0 || $discount < 0) {
                    throw ValidationException::withMessages([
                        'applications' => 'Los importes aplicados no pueden ser negativos.',
                    ]);
                }

                if ($amount <= 0 && $discount <= 0) {
                    throw ValidationException::withMessages([
                        'applications' => 'Todos los importes aplicados deben ser mayores a cero.',
                    ]);
                }

                $coverage = round($amount + $discount, 2);

                if ($coverage > (float) $charge->balance) {
                    throw ValidationException::withMessages([
                        'applications' => 'No puedes aplicar un importe mayor al saldo pendiente del cargo.',
                    ]);
                }

                if (!$charge->allows_partial_payments && $coverage !== round((float) $charge->balance, 2)) {
                    throw ValidationException::withMessages([
                        'applications' => 'Los cargos que no admiten parcialidades deben liquidarse completos.',
                    ]);
                }

                return [
                    'charge' => $charge,
                    'amount' => $amount,
                    'discount' => $discount,
                ];
            })
            ->filter()
            ->values();

        if ($normalizedApplications->isEmpty()) {
            throw ValidationException::withMessages([
                'applications' => 'Selecciona al menos un cargo para registrar el cobro.',
            ]);
        }

        return $normalizedApplications;
    }
}
