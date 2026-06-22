<?php

namespace App\Domain\Spreadsheet\Events;

use App\Domain\Spreadsheet\Models\NamedRange;
use App\Domain\Spreadsheet\Models\Workbook;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NamedRangeChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Workbook $workbook,
        public NamedRange $namedRange,
        public string $action,
    ) {}
}
