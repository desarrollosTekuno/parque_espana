<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CombinedIncomeReportExport implements WithMultipleSheets {
    public function __construct(
        protected string $sourceClubName,
        protected string $targetClubName,
        protected int $targetClubId,
        protected Carbon $startDate,
        protected Carbon $endDate,
        protected Collection $payments,
        protected string $deliveredBy,
        protected ?string $logoContent = null,
    ) {}

    public function sheets(): array {
        return [
            new IncomeReportExport(
                $this->sourceClubName,
                $this->startDate,
                $this->endDate,
                $this->payments,
                $this->deliveredBy,
                $this->logoContent,
            ),
            new InterclubIncomeReportExport(
                $this->sourceClubName,
                $this->targetClubName,
                $this->targetClubId,
                $this->startDate,
                $this->endDate,
                $this->payments,
                $this->deliveredBy,
                $this->logoContent,
            ),
        ];
    }
}
