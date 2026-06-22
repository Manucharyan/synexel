<?php

namespace App\Domain\Spreadsheet\Events;

use App\Domain\Spreadsheet\Models\Chart;
use App\Domain\Spreadsheet\Models\Workbook;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChartChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Workbook $workbook,
        public Chart $chart,
        public string $action,
    ) {}
}
