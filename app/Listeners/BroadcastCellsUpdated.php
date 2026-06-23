<?php

namespace App\Listeners;

use App\Domain\Spreadsheet\Events\CellsUpdated;
use App\Domain\Spreadsheet\Events\CellsUpdatedBroadcast;
use App\Support\AuditContext;

class BroadcastCellsUpdated
{
    public function handle(CellsUpdated $event): void
    {
        $user = AuditContext::user();

        event(new CellsUpdatedBroadcast(
            $event->workbook,
            $event->sheet,
            $event->operationId,
            $event->changes,
            $user?->id ?? 0,
            $user?->name ?? 'Unknown',
        ));
    }
}
