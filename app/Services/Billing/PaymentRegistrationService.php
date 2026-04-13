<?php

namespace App\Services\Billing;

use App\Models\Billing\Charge;
use App\Models\Billing\ClubPaymentMethod;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use App\Models\Billing\PaymentMethod;
use App\Models\Memberships\MembershipAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentRegistrationService
{
    public function register(
        MembershipAccount $account,
        int $clubId,
        PaymentMethod $paymentMethod,
        array $applications,
        string $paidAt,
        ?string $reference,
        ?string $bankName,
        ?string $checkNumber,
        ?string $notes,
        ?int $receivedBy,
        ?int $sessionClubId = null
    ): Payment {
        $charges = $this->resolveCharges($account->id, $applications);

        $this->ensureChargesBelongToClub($charges, $clubId);
        $this->ensurePaymentMethodAllowedForClub($paymentMethod->id, $clubId);
        $this->validateMethodRequirements($paymentMethod, $reference, $bankName, $checkNumber);
        $normalizedApplications = $this->normalizeApplications($charges, $applications);

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
            $sessionClubId
        ) {
            $totalAmount = round($normalizedApplications->sum('amount'), 2);

            $payment = Payment::create([
                'membership_account_id' => $account->id,
                'club_id' => $clubId,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $totalAmount,
                'paid_at' => $paidAt,
                'reference' => $reference,
                'bank_name' => $bankName,
                'check_number' => $checkNumber,
                'notes' => $notes,
                'received_by' => $receivedBy,
                'status' => 'registered',
                'metadata' => [
                    'session_club_id' => $sessionClubId,
                ],
            ]);

            $normalizedApplications->each(function (array $application) use ($payment) {
                $charge = $application['charge'];
                $amount = $application['amount'];
                $newBalance = round((float) $charge->balance - $amount, 2);

                PaymentApplication::create([
                    'payment_id' => $payment->id,
                    'charge_id' => $charge->id,
                    'applied_amount' => $amount,
                ]);

                $charge->update([
                    'balance' => $newBalance,
                    'status' => $newBalance <= 0 ? 'paid' : 'partial',
                ]);
            });

            return $payment;
        });
    }

    protected function resolveCharges(int $accountId, array $applications): Collection
    {
        $chargeIds = collect($applications)
            ->pluck('charge_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $charges = Charge::query()
            ->with('membership.club')
            ->where('membership_account_id', $accountId)
            ->whereIn('id', $chargeIds)
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

    protected function ensureChargesBelongToClub(Collection $charges, int $clubId): void
    {
        $invalidCharge = $charges->first(function (Charge $charge) use ($clubId) {
            return (int) ($charge->membership?->club_id ?? 0) !== $clubId;
        });

        if ($invalidCharge) {
            throw ValidationException::withMessages([
                'club_id' => 'Todos los cargos seleccionados deben pertenecer al mismo parque.',
            ]);
        }
    }

    protected function ensurePaymentMethodAllowedForClub(int $paymentMethodId, int $clubId): void
    {
        $isAllowed = ClubPaymentMethod::query()
            ->where('club_id', $clubId)
            ->where('payment_method_id', $paymentMethodId)
            ->where('is_active', true)
            ->exists();

        if (!$isAllowed) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'El metodo de pago seleccionado no esta habilitado para este parque.',
            ]);
        }
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
            $errors['check_number'] = 'Debes capturar el numero de cheque.';
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
                $amount = round((float) ($application['amount'] ?? 0), 2);

                if (!$charge) {
                    return null;
                }

                if ($amount <= 0) {
                    throw ValidationException::withMessages([
                        'applications' => 'Todos los importes aplicados deben ser mayores a cero.',
                    ]);
                }

                if ($amount > (float) $charge->balance) {
                    throw ValidationException::withMessages([
                        'applications' => 'No puedes aplicar un importe mayor al saldo pendiente del cargo.',
                    ]);
                }

                if (!$charge->allows_partial_payments && $amount !== round((float) $charge->balance, 2)) {
                    throw ValidationException::withMessages([
                        'applications' => 'Los cargos que no admiten parcialidades deben liquidarse completos.',
                    ]);
                }

                return [
                    'charge' => $charge,
                    'amount' => $amount,
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
