<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\Events\FilterApplied;
use App\Domain\Spreadsheet\Events\RangeSorted;
use App\Domain\Spreadsheet\Models\Cell;
use App\Domain\Spreadsheet\Models\Sheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SortFilterService
{
    public function __construct(
        private readonly CellBatchService $cellBatchService,
    ) {}

    public function sort(Sheet $sheet, string $range, int $column, string $order = 'asc'): array
    {
        $ref = RangeRef::fromA1($range);
        $sortCol = $ref->startCol + $column - 1;

        $cells = Cell::query()
            ->where('sheet_id', $sheet->id)
            ->whereBetween('row', [$ref->startRow, $ref->endRow])
            ->whereBetween('col', [$ref->startCol, $ref->endCol])
            ->get();

        $rows = [];
        for ($r = $ref->startRow; $r <= $ref->endRow; $r++) {
            $rows[$r] = [];
        }

        foreach ($cells as $cell) {
            $rows[$cell->row][$cell->col] = $cell;
        }

        $rowKeys = array_keys($rows);
        usort($rowKeys, function ($a, $b) use ($rows, $sortCol, $order) {
            $valA = $rows[$a][$sortCol]->displayValue() ?? '';
            $valB = $rows[$b][$sortCol]->displayValue() ?? '';

            $cmp = is_numeric($valA) && is_numeric($valB)
                ? (float) $valA <=> (float) $valB
                : strcmp((string) $valA, (string) $valB);

            return $order === 'desc' ? -$cmp : $cmp;
        });

        $operationId = 'op_'.Str::ulid();
        $updates = [];

        DB::transaction(function () use ($sheet, $rows, $rowKeys, $ref, $operationId, &$updates) {
            $sortedData = [];
            foreach ($rowKeys as $originalRow) {
                $sortedData[] = $rows[$originalRow];
            }

            Cell::query()
                ->where('sheet_id', $sheet->id)
                ->whereBetween('row', [$ref->startRow, $ref->endRow])
                ->whereBetween('col', [$ref->startCol, $ref->endCol])
                ->delete();

            $targetRow = $ref->startRow;
            foreach ($sortedData as $rowData) {
                foreach ($rowData as $col => $cell) {
                    $updates[] = [
                        'row' => $targetRow,
                        'col' => $col,
                        'value' => $cell->formula ? null : $cell->raw_value,
                        'formula' => $cell->formula,
                        'style' => $cell->style,
                    ];
                }
                $targetRow++;
            }
        });

        $result = $this->cellBatchService->batchUpdate($sheet->fresh(), $updates, $operationId, true, 'sort');

        event(new RangeSorted(
            $sheet->workbook,
            $sheet,
            $range,
            $column,
            $order,
            $result['operation_id'],
        ));

        return $result;
    }

    public function applyFilter(Sheet $sheet, array $rules): Sheet
    {
        $hiddenRows = [];

        if (! empty($rules['hide_rows'])) {
            $hiddenRows = array_map('intval', $rules['hide_rows']);
        }

        $layout = $sheet->layout ?? [];
        $layout['hidden_rows'] = $hiddenRows;
        $layout['filter_rules'] = $rules['criteria'] ?? [];

        $sheet->update(['layout' => $layout]);

        event(new FilterApplied($sheet->workbook, $sheet->fresh(), count($hiddenRows)));

        return $sheet->fresh();
    }
}
