<?php

namespace App\Domain\Spreadsheet\Events;

use App\Domain\Spreadsheet\Models\Workbook;
use App\Domain\Spreadsheet\Models\WorkbookShare;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkbookShared
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Workbook $workbook,
        public WorkbookShare $share,
    ) {}
}
