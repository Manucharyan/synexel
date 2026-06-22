<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\Events\SheetCreated;
use App\Domain\Spreadsheet\Events\SheetDeleted;
use App\Domain\Spreadsheet\Events\SheetLayoutChanged;
use App\Domain\Spreadsheet\Events\SheetRenamed;
use App\Domain\Spreadsheet\Events\WorkbookCreated;
use App\Domain\Spreadsheet\Events\WorkbookDeleted;
use App\Domain\Spreadsheet\Events\WorkbookUpdated;
use App\Domain\Spreadsheet\Models\CellChange;
use App\Domain\Spreadsheet\Models\Sheet;
use App\Domain\Spreadsheet\Models\Workbook;
use App\Models\User;

class WorkbookService
{
    public function listForUser(User $user)
    {
        return Workbook::query()
            ->where('user_id', $user->id)
            ->withCount('sheets')
            ->latest()
            ->get();
    }

    public function create(User $user, string $name, array $metadata = []): Workbook
    {
        $workbook = Workbook::create([
            'user_id' => $user->id,
            'name' => $name,
            'metadata' => $metadata,
        ]);

        $this->createSheet($workbook, 'Sheet1', 0, dispatchEvent: false);

        event(new WorkbookCreated($workbook));

        return $workbook->load('sheets');
    }

    public function findForUser(User $user, string $id): Workbook
    {
        return Workbook::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();
    }

    public function update(Workbook $workbook, array $data): Workbook
    {
        $changes = [];

        if (isset($data['name']) && $data['name'] !== $workbook->name) {
            $changes['name'] = ['from' => $workbook->name, 'to' => $data['name']];
        }

        $workbook->update($data);

        if ($changes !== []) {
            event(new WorkbookUpdated($workbook->fresh(), $changes));
        }

        return $workbook->fresh();
    }

    public function delete(Workbook $workbook): void
    {
        event(new WorkbookDeleted($workbook));
        $workbook->delete();
    }

    public function createSheet(Workbook $workbook, string $name, ?int $index = null, bool $dispatchEvent = true): Sheet
    {
        if ($index === null) {
            $index = (int) $workbook->sheets()->max('index') + 1;
        }

        $sheet = $workbook->sheets()->create([
            'name' => $this->uniqueSheetName($workbook, $name),
            'index' => $index,
            'layout' => [],
        ]);

        if ($dispatchEvent) {
            event(new SheetCreated($workbook, $sheet));
        }

        return $sheet;
    }

    public function updateSheet(Sheet $sheet, array $data): Sheet
    {
        $oldName = $sheet->name;
        $oldLayout = $sheet->layout ?? [];
        $sheet->update($data);

        if (isset($data['name']) && $data['name'] !== $oldName) {
            event(new SheetRenamed($sheet->workbook, $sheet, $oldName));
        }

        if (isset($data['layout']) && $data['layout'] !== $oldLayout) {
            $layoutChanges = $this->diffLayout($oldLayout, $data['layout']);
            if ($layoutChanges !== []) {
                event(new SheetLayoutChanged($sheet->workbook, $sheet, $layoutChanges));
            }
        }

        return $sheet->fresh();
    }

    public function deleteSheet(Sheet $sheet): void
    {
        $workbook = $sheet->workbook;
        $sheetName = $sheet->name;
        $sheetId = $sheet->id;

        $sheet->delete();

        event(new SheetDeleted($workbook, $sheetName, $sheetId));
    }

    private function diffLayout(array $before, array $after): array
    {
        $changes = [];

        foreach (['merged_cells', 'hidden_rows', 'filters'] as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old != $new) {
                $changes[$key] = ['from' => $old, 'to' => $new];
            }
        }

        return $changes;
    }

    private function uniqueSheetName(Workbook $workbook, string $preferred): string
    {
        $existing = $workbook->sheets()->pluck('name')->all();

        if (! in_array($preferred, $existing, true)) {
            return $preferred;
        }

        $base = preg_replace('/\s*\d+$/', '', $preferred) ?: 'Sheet';
        $base = rtrim($base);

        for ($i = 2; $i <= 999; $i++) {
            foreach ([$base.$i, $base.' '.$i] as $candidate) {
                if (! in_array($candidate, $existing, true)) {
                    return $candidate;
                }
            }
        }

        return $base.' '.uniqid();
    }

    public function history(Workbook $workbook, int $limit = 50)
    {
        return CellChange::query()
            ->where('workbook_id', $workbook->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->groupBy('operation_id')
            ->map(fn ($changes) => [
                'operation_id' => $changes->first()->operation_id,
                'created_at' => $changes->first()->created_at,
                'changes_count' => $changes->count(),
                'reverted' => $changes->every(fn ($c) => $c->reverted),
            ])
            ->values();
    }
}
