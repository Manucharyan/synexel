<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\Models\CellChange;
use App\Domain\Spreadsheet\Models\Workbook;
use App\Models\User;
use Carbon\Carbon;

class WorkbookSyncService
{
    public function changesSince(Workbook $workbook, User $user, ?string $since = null, ?string $excludeOperationId = null): array
    {
        $sinceTime = $since ? Carbon::parse($since) : now()->subMinutes(2);

        $query = CellChange::query()
            ->where('workbook_id', $workbook->id)
            ->where('created_at', '>', $sinceTime)
            ->where('reverted', false)
            ->orderBy('created_at');

        if ($excludeOperationId) {
            $query->where('operation_id', '!=', $excludeOperationId);
        }

        $changes = $query->get();

        return $changes
            ->groupBy('operation_id')
            ->map(fn ($group) => [
                'operation_id' => $group->first()->operation_id,
                'sheet_id' => $group->first()->sheet_id,
                'created_at' => $group->first()->created_at?->toIso8601String(),
                'cells' => $group->map(fn (CellChange $change) => [
                    'row' => $change->row,
                    'col' => $change->col,
                    'before' => $change->before,
                    'after' => $change->after,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
