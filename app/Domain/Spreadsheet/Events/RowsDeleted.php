<?php

namespace App\Domain\Spreadsheet\Events;

use App\Domain\Spreadsheet\Models\Sheet;
use App\Domain\Spreadsheet\Models\Workbook;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RowsDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Workbook $workbook,
        public Sheet $sheet,
        public int $startRow,
        public int $endRow,
    ) {}
}
