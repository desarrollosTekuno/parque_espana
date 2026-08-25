<?php

namespace App\Console\Commands;

use App\Models\Billing\Charge;
use App\Models\Billing\PaymentMethod;
use App\Models\Members\Member;
use App\Models\Members\MemberPaymentSource;
use App\Models\Memberships\MembershipAccount;
use App\Services\Billing\PaymentRegistrationService;
use App\Services\Payments\ConektaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessDomiciliatedPayments extends Command
{
    protected $signature = 'billing:process-domiciliated-payments
        {--date=        : Período a cobrar en formato YYYY-MM-DD (default: mes actual)}
        {--club=        : Solo procesar un club específico (ID)}
        {--dry-run      : Muestra lo que se cobraría sin realizar el cobro}';

    protected $description = 'Cobra automáticamente las mensualidades pendientes a socios con tarjeta domiciliada.';

    private int $charged    = 0;
    private int $skipped    = 0;
    private int $failed     = 0;

    public function __construct(
        private ConektaService             $conekta,
        private PaymentRegistrationService $paymentService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $periodDate = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfMonth()
            : now()->startOfMonth();

        $clubId = $this->option('club') ? (int) $this->option('club') : null;
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Procesando cobros domiciliados para %s%s%s',
            $periodDate->translatedFormat('F Y'),
            $clubId ? " | Club #{$clubId}" : '',
            $dryRun ? ' (dry-run)' : ''
        ));

        // 1. Obtener socios con tarjeta predeterminada activa
        $sources = MemberPaymentSource::query()
            ->with('member')
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->get();

        $this->info("Socios con tarjeta domiciliada: {$sources->count()}");
        $this->newLine();

        foreach ($sources as $source) {
            $this->processMemberSource($source, $periodDate, $clubId, $dryRun);
        }

        $this->newLine();
        $this->table(
            ['Resultado', 'Cantidad'],
            [
                ['Cobrados',  $this->charged],
                ['Sin cargos pendientes', $this->skipped],
                ['Con error', $this->failed],
            ]
        );

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────
    // Procesa un socio
    // ──────────────────────────────────────────────────────────────

    private function processMemberSource(
        MemberPaymentSource $source,
        Carbon $periodDate,
        ?int $filterClubId,
        bool $dryRun
    ): void {
        $member = $source->member;

        if (!$member) {
            $this->skipped++;
            return;
        }

        // La tarjeta solo es válida en la cuenta Conekta del club donde se
        // tokenizó — no se puede usar para cobrar cargos de otro club.
        $clubId = (int) $source->club_id;

        if ($filterClubId && $clubId !== $filterClubId) {
            return;
        }

        // Cuenta del socio con membresía activa en ese mismo club
        $account = $this->resolveActiveAccount($member, $clubId);

        if (!$account) {
            $this->skipped++;
            return;
        }

        $this->processAccountClubCharges($account, $source, $clubId, $periodDate, $dryRun);
    }

    // ──────────────────────────────────────────────────────────────
    // Cobra los cargos pendientes de una cuenta en el club de la tarjeta
    // ──────────────────────────────────────────────────────────────

    private function processAccountClubCharges(
        MembershipAccount $account,
        MemberPaymentSource $source,
        int $clubId,
        Carbon $periodDate,
        bool $dryRun
    ): void {
        $charges = $this->resolvePendingCharges($account, $clubId, $periodDate);

        if ($charges->isEmpty()) {
            $this->skipped++;
            return;
        }

        $this->chargeClubPendingCharges(
            account:    $account,
            source:     $source,
            clubId:     $clubId,
            charges:    $charges,
            periodDate: $periodDate,
            dryRun:     $dryRun,
        );
    }

    private function chargeClubPendingCharges(
        MembershipAccount $account,
        MemberPaymentSource $source,
        int $clubId,
        Collection $charges,
        Carbon $periodDate,
        bool $dryRun
    ): void {
        $paymentMethod = $this->resolveConektaPaymentMethod($clubId);

        if (!$paymentMethod) {
            $this->warn("Club #{$clubId}: Conekta no habilitado — omitiendo cuenta #{$account->membership_number}");
            $this->skipped++;
            return;
        }

        $totalAmount = round($charges->sum('balance'), 2);
        $amountCents = (int) round($totalAmount * 100);
        $chargeIds   = $charges->pluck('id');

        $this->line(sprintf(
            '  %-12s | Club #%-3d | $%-10s | %s',
            $account->membership_number ?? "Cuenta #{$account->id}",
            $clubId,
            number_format($totalAmount, 2),
            $source->card_brand . ' *' . $source->card_last4,
        ));

        if ($dryRun) {
            $this->charged++;
            return;
        }

        try {
            // Cobrar en Conekta
            $member      = $source->member;
            $description = $this->buildDescription($account, $charges);

            $conektaResult = $this->conekta->charge(
                member:      $member,
                source:      $source,
                clubId:      $clubId,
                amountCents: $amountCents,
                description: $description,
                metadata: [
                    'membership_account_id' => $account->id,
                    'club_id'               => $clubId,
                    'charge_ids'            => $chargeIds->join(','),
                    'period'                => $periodDate->format('Y-m'),
                    'origin'                => 'domiciliated_cron',
                ],
            );

            if ($conektaResult['status'] !== 'paid') {
                $this->error("    ✗ Rechazado por Conekta (status: {$conektaResult['status']})");
                $this->logFailure($account, $clubId, $totalAmount, "Conekta status: {$conektaResult['status']}");
                $this->failed++;
                return;
            }

            // Registrar el pago en billing
            $applications = $charges->map(fn (Charge $c) => [
                'charge_id' => $c->id,
                'amount'    => round((float) $c->balance, 2),
            ])->values()->toArray();

            $this->paymentService->register(
                account:       $account,
                clubId:        $clubId,
                paymentMethod: $paymentMethod,
                applications:  $applications,
                paidAt:        now()->toDateString(),
                reference:     $conektaResult['order_id'],
                bankName:      null,
                checkNumber:   null,
                notes:         "Cargo automático domiciliado. Orden Conekta: {$conektaResult['order_id']}. Cargo: {$conektaResult['charge_id']}. Período: {$periodDate->format('Y-m')}.",
                receivedBy:    null,
                sessionClubId: $clubId,
            );

            $this->info("    ✓ Cobrado — Orden: {$conektaResult['order_id']}");
            $this->charged++;

        } catch (Throwable $e) {
            $this->error("    ✗ Error: {$e->getMessage()}");
            $this->logFailure($account, $clubId, $totalAmount, $e->getMessage());
            $this->failed++;
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Cuenta activa del socio (titular principal) en un club específico.
     */
    private function resolveActiveAccount(Member $member, int $clubId): ?MembershipAccount
    {
        return MembershipAccount::query()
            ->whereHas('primaryHolder', fn ($q) => $q->where('member_id', $member->id))
            ->whereHas('memberships', fn ($q) => $q
                ->where('status', 'active')
                ->where('is_primary', true)
                ->where('club_id', $clubId)
            )
            ->first();
    }

    /**
     * Cargos pendientes del período para la cuenta, en un club específico.
     */
    private function resolvePendingCharges(MembershipAccount $account, int $clubId, Carbon $periodDate): Collection
    {
        return Charge::query()
            ->with('membership')
            ->where('membership_account_id', $account->id)
            ->whereHas('membership', fn ($q) => $q->where('club_id', $clubId))
            ->whereIn('status', ['pending', 'partial'])
            ->where('period_year',  (int) $periodDate->format('Y'))
            ->where('period_month', (int) $periodDate->format('m'))
            ->where('balance', '>', 0)
            ->get();
    }

    /**
     * Método de pago Conekta activo para el club.
     */
    private function resolveConektaPaymentMethod(int $clubId): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('provider', PaymentMethod::PROVIDER_CONEKTA)
            ->where('is_active', true)
            ->whereHas('clubPaymentMethods', fn ($q) =>
                $q->where('club_id', $clubId)->where('is_active', true)
            )
            ->first();
    }

    /**
     * Descripción del cargo para Conekta.
     */
    private function buildDescription(MembershipAccount $account, Collection $charges): string
    {
        $concepts = $charges
            ->map(fn (Charge $c) => $c->description ?? "Cargo #{$c->id}")
            ->join(', ');

        return "Cuenta #{$account->membership_number}: {$concepts}";
    }

    /**
     * Registra el fallo en el log.
     */
    private function logFailure(MembershipAccount $account, int $clubId, float $amount, string $reason): void
    {
        Log::channel('stack')->error('ProcessDomiciliatedPayments: fallo al cobrar', [
            'membership_account_id' => $account->id,
            'membership_number'     => $account->membership_number,
            'club_id'               => $clubId,
            'amount'                => $amount,
            'reason'                => $reason,
        ]);
    }
}
