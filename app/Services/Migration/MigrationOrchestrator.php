<?php

namespace App\Services\Migration;

use App\Models\Administrator\Club;
use App\Models\Billing\ChargeConcept;
use App\Models\Billing\PaymentMethod;
use App\Models\Members\Member;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Memberships\MembershipType;
use App\Services\Billing\MembershipPricingService;
use App\Services\Migration\Importers\CasilleroImporter;
use App\Services\Migration\Importers\DomicilioImporter;
use App\Services\Migration\Importers\EmpleoImporter;
use App\Services\Migration\Importers\HistorialImporter;
use App\Services\Migration\Importers\IntegranteImporter;
use App\Services\Migration\Importers\MembershipImporter;
use App\Services\Migration\Importers\UserImporter;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MigrationOrchestrator
{
    public function __construct(
        protected MembershipPricingService $pricingService
    ) {}

    /** Todos los importadores en el orden en que deben ejecutarse. */
    private function importers(): array
    {
        return [
            new UserImporter(),
            new MembershipImporter($this->pricingService),
            new IntegranteImporter(),
            new DomicilioImporter(),
            new EmpleoImporter(),
            new HistorialImporter(),
            new CasilleroImporter(),
        ];
    }

    /**
     * @param  string   $filePath  Ruta al archivo .xlsx
     * @param  bool     $dryRun    Si true, hace rollback al final (simulación)
     * @param  string[] $only      Si no está vacío, solo ejecuta los importadores listados
     * @return MigrationReport
     */
    public function run(string $filePath, bool $dryRun = false, array $only = []): MigrationReport
    {
        $report = new MigrationReport();

        $spreadsheet = IOFactory::load($filePath);

        $context = new MigrationContext();
        $this->preloadCatalogs($context);
        $this->preloadFromDatabase($context);

        // Dry-run: abrimos la transacción manualmente para poder hacer rollback al final.
        // Normal:  DB::transaction() se encarga del commit/rollback automáticamente.
        if ($dryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($this->importers() as $importer) {
                if (!empty($only)) {
                    $importerSlug = strtolower(\Illuminate\Support\Str::ascii($importer->name()));
                    $onlySlugs    = array_map(fn($s) => strtolower(\Illuminate\Support\Str::ascii($s)), $only);

                    if (!in_array($importerSlug, $onlySlugs)) {
                        continue;
                    }
                }

                $sheetCount = $spreadsheet->getSheetCount();
                if ($importer->sheetIndex() >= $sheetCount) {
                    $report->record($importer->name(), 0, [
                        ['row' => 0, 'message' => "Hoja índice {$importer->sheetIndex()} no existe en el archivo."],
                    ]);
                    continue;
                }

                $sheet = $spreadsheet->getSheet($importer->sheetIndex());
                $importer->import($sheet, $context, $report, $dryRun);
            }

            if ($dryRun) {
                DB::rollBack(); // Descartar todo — era solo simulación
            }
        } catch (\Throwable $e) {
            if ($dryRun) {
                DB::rollBack();
            }
            throw $e;
        }

        return $report;
    }

    // ── Pre-carga desde base de datos ──────────────────────────────────────────

    /**
     * Reconstruye el contexto leyendo lo que ya existe en BD.
     * Permite correr importadores de forma individual sin perder referencias.
     */
    private function preloadFromDatabase(MigrationContext $context): void
    {
        // members.migration_origin_id → member.id
        Member::whereNotNull('migration_origin_id')
            ->select('id', 'migration_origin_id')
            ->each(function (Member $member) use ($context) {
                $context->membersByOriginId[$member->migration_origin_id] = $member->id;
            });

        // membership_number → account.id  +  membership por account+club
        MembershipAccount::with(['memberships'])
            ->select('id', 'membership_number')
            ->each(function (MembershipAccount $account) use ($context) {
                $context->accountsByNumber[$account->membership_number] = $account->id;

                foreach ($account->memberships as $membership) {
                    $key = "{$account->membership_number}_{$membership->club_id}";
                    $context->membershipByAccountAndClub[$key] = $membership->id;
                }
            });

        // Titular principal por cuenta
        MembershipAccountMember::where('is_primary_holder', true)
            ->with('membershipAccount:id,membership_number')
            ->each(function (MembershipAccountMember $accountMember) use ($context) {
                $number = $accountMember->membershipAccount?->membership_number;
                if ($number) {
                    $context->primaryHolderByAccount[$number] = $accountMember->member_id;
                }
            });
    }

    // ── Pre-carga de catálogos ──────────────────────────────────────────────────

    private function preloadCatalogs(MigrationContext $context): void
    {
        // Clubs
        Club::all()->each(function ($club) use ($context) {
            $context->clubsByCode[strtoupper($club->code)] = $club->id;
        });

        // Tipos de membresía
        MembershipType::all()->each(function ($type) use ($context) {
            $context->membershipTypesByCode[strtoupper($type->code)] = $type->id;
        });

        // Conceptos de cobro
        ChargeConcept::all()->each(function ($concept) use ($context) {
            $context->conceptsByCode[strtoupper($concept->code)]    = $concept->id;
            $context->conceptAllowsPartial[$concept->id]            = (bool) $concept->allows_partial_payments;
        });

        // Métodos de pago
        PaymentMethod::where('is_active', true)->each(function ($method) use ($context) {
            $context->paymentMethodsByCode[strtoupper($method->code)] = $method->id;
        });
    }
}
