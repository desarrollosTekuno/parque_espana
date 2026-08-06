<?php

namespace App\Exports;

use App\Models\Billing\CashCut;
use App\Exports\CashCutSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CashCutExport implements WithMultipleSheets
{
    public function __construct(
        protected CashCut $cashCut
    ) {
    }

    public function sheets(): array
    {
        return [
            new CashCutSheet(
            $this->cashCut->date,
            $this->cashCut->user_id,
            1
        ),

        new CashCutSheet(
            $this->cashCut->date,
            $this->cashCut->user_id,
            2
        ),
        ];
    }
}