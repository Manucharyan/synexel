<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\Events\ColumnsDeleted;
use App\Domain\Spreadsheet\Events\ColumnsInserted;
use App\Domain\Spreadsheet\Events\RowsDeleted;
use App\Domain\Spreadsheet\Events\RowsInserted;
use App\Domain\Spreadsheet\DTOs\A1Notation;
use App\Domain\Spreadsheet\Models\Cell;
use App\Domain\Spreadsheet\Models\Sheet;
use Illuminate\Support\Facades\DB;

class SheetStructureService
{
    public function __construct(
        private readonly FormulaEvaluationService $formulaService,
    ) {}

    public function deleteRows(Sheet $sheet, int $startRow, int $endRow): array
    {
        if ($startRow < 1 || $endRow < $startRow) {
            throw new \InvalidArgumentException('Invalid row range.');
        }

        $count = $endRow - $startRow + 1;

        DB::transaction(function () use ($sheet, $startRow, $endRow, $count) {
            Cell::query()
                ->where('sheet_id', $sheet->id)
                ->whereBetween('row', [$startRow, $endRow])
                ->delete();

            Cell::query()
                ->where('sheet_id', $sheet->id)
                ->where('row', '>', $endRow)
                ->decrement('row', $count);

            $this->adjustMergedRows($sheet, $startRow, $endRow, $count);
        });

        $this->formulaService->recalculateSheet($sheet->fresh());

        event(new RowsDeleted($sheet->workbook, $sheet, $startRow, $endRow));

        return [
            'deleted_rows' => $count,
            'start_row' => $startRow,
            'end_row' => $endRow,
            'layout' => $sheet->fresh()->layout,
        ];
    }

    public function deleteColumns(Sheet $sheet, int $startCol, int $endCol): array
    {
        if ($startCol < 1 || $endCol < $startCol) {
            throw new \InvalidArgumentException('Invalid column range.');
        }

        $count = $endCol - $startCol + 1;

        DB::transaction(function () use ($sheet, $startCol, $endCol, $count) {
            Cell::query()
                ->where('sheet_id', $sheet->id)
                ->whereBetween('col', [$startCol, $endCol])
                ->delete();

            Cell::query()
                ->where('sheet_id', $sheet->id)
                ->where('col', '>', $endCol)
                ->decrement('col', $count);

            $this->adjustMergedColumns($sheet, $startCol, $endCol, $count);
        });

        $this->formulaService->recalculateSheet($sheet->fresh());

        event(new ColumnsDeleted($sheet->workbook, $sheet, $startCol, $endCol));

        return [
            'deleted_columns' => $count,
            'start_col' => $startCol,
            'end_col' => $endCol,
            'layout' => $sheet->fresh()->layout,
        ];
    }

    public function insertRows(Sheet $sheet, int $atRow, int $count = 1): array
    {
        if ($atRow < 1 || $count < 1) {
            throw new \InvalidArgumentException('Invalid row insert position.');
        }

        DB::transaction(function () use ($sheet, $atRow, $count) {
            $cells = Cell::query()
                ->where('sheet_id', $sheet->id)
                ->where('row', '>=', $atRow)
                ->orderByDesc('row')
                ->get();

            foreach ($cells as $cell) {
                $cell->update(['row' => $cell->row + $count]);
            }

            $this->adjustMergedRowsInsert($sheet, $atRow, $count);
        });

        $this->formulaService->recalculateSheet($sheet->fresh());

        event(new RowsInserted($sheet->workbook, $sheet, $atRow, $count));

        return ['inserted_rows' => $count, 'at_row' => $atRow, 'layout' => $sheet->fresh()->layout];
    }

    public function insertColumns(Sheet $sheet, int $atCol, int $count = 1): array
    {
        if ($atCol < 1 || $count < 1) {
            throw new \InvalidArgumentException('Invalid column insert position.');
        }

        DB::transaction(function () use ($sheet, $atCol, $count) {
            $cells = Cell::query()
                ->where('sheet_id', $sheet->id)
                ->where('col', '>=', $atCol)
                ->orderByDesc('col')
                ->get();

            foreach ($cells as $cell) {
                $cell->update(['col' => $cell->col + $count]);
            }

            $this->adjustMergedColumnsInsert($sheet, $atCol, $count);
        });

        $this->formulaService->recalculateSheet($sheet->fresh());

        event(new ColumnsInserted($sheet->workbook, $sheet, $atCol, $count));

        return ['inserted_columns' => $count, 'at_col' => $atCol, 'layout' => $sheet->fresh()->layout];
    }

    private function adjustMergedRows(Sheet $sheet, int $startRow, int $endRow, int $count): void
    {
        $layout = $sheet->layout ?? [];
        $merged = $layout['merged_cells'] ?? [];
        $updated = [];

        foreach ($merged as $rangeA1) {
            $parsed = A1Notation::parseRange($rangeA1);
            $adjusted = $this->adjustRowSpan(
                $parsed['start_row'],
                $parsed['end_row'],
                $startRow,
                $endRow,
                $count,
            );

            if ($adjusted === null) {
                continue;
            }

            [$r1, $r2] = $adjusted;
            $this->pushMergeRange(
                $updated,
                $r1,
                $parsed['start_col'],
                $r2,
                $parsed['end_col'],
            );
        }

        $layout['merged_cells'] = $updated;
        $sheet->update(['layout' => $layout]);
    }

    private function adjustMergedColumns(Sheet $sheet, int $startCol, int $endCol, int $count): void
    {
        $layout = $sheet->layout ?? [];
        $merged = $layout['merged_cells'] ?? [];
        $updated = [];

        foreach ($merged as $rangeA1) {
            $parsed = A1Notation::parseRange($rangeA1);
            $adjusted = $this->adjustColSpan(
                $parsed['start_col'],
                $parsed['end_col'],
                $startCol,
                $endCol,
                $count,
            );

            if ($adjusted === null) {
                continue;
            }

            [$c1, $c2] = $adjusted;
            $this->pushMergeRange(
                $updated,
                $parsed['start_row'],
                $c1,
                $parsed['end_row'],
                $c2,
            );
        }

        $layout['merged_cells'] = $updated;
        $sheet->update(['layout' => $layout]);
    }

    /** @return array{0:int,1:int}|null */
    private function adjustRowSpan(int $r1, int $r2, int $delStart, int $delEnd, int $count): ?array
    {
        if ($r2 < $delStart) {
            return [$r1, $r2];
        }

        if ($r1 > $delEnd) {
            return [$r1 - $count, $r2 - $count];
        }

        if ($r1 >= $delStart && $r2 <= $delEnd) {
            return null;
        }

        if ($r1 < $delStart) {
            if ($r2 <= $delEnd) {
                $r2 = $delStart - 1;
            } else {
                $r2 -= $count;
            }

            return $r1 <= $r2 ? [$r1, $r2] : null;
        }

        // Top of merge falls inside deleted rows.
        $r1 = $delStart;
        $r2 -= $count;

        return $r1 <= $r2 ? [$r1, $r2] : null;
    }

    /** @return array{0:int,1:int}|null */
    private function adjustColSpan(int $c1, int $c2, int $delStart, int $delEnd, int $count): ?array
    {
        if ($c2 < $delStart) {
            return [$c1, $c2];
        }

        if ($c1 > $delEnd) {
            return [$c1 - $count, $c2 - $count];
        }

        if ($c1 >= $delStart && $c2 <= $delEnd) {
            return null;
        }

        if ($c1 < $delStart) {
            if ($c2 <= $delEnd) {
                $c2 = $delStart - 1;
            } else {
                $c2 -= $count;
            }

            return $c1 <= $c2 ? [$c1, $c2] : null;
        }

        $c1 = $delStart;
        $c2 -= $count;

        return $c1 <= $c2 ? [$c1, $c2] : null;
    }

    /** @param list<string> $updated */
    private function pushMergeRange(array &$updated, int $r1, int $c1, int $r2, int $c2): void
    {
        if ($r1 > $r2 || $c1 > $c2 || ($r1 === $r2 && $c1 === $c2)) {
            return;
        }

        $updated[] = A1Notation::fromRange($r1, $c1, $r2, $c2);
    }

    private function adjustMergedRowsInsert(Sheet $sheet, int $atRow, int $count): void
    {
        $layout = $sheet->layout ?? [];
        $merged = $layout['merged_cells'] ?? [];
        $updated = [];

        foreach ($merged as $rangeA1) {
            $parsed = A1Notation::parseRange($rangeA1);
            $r1 = $parsed['start_row'];
            $r2 = $parsed['end_row'];
            $c1 = $parsed['start_col'];
            $c2 = $parsed['end_col'];

            if ($r2 < $atRow) {
                $updated[] = $rangeA1;
                continue;
            }

            if ($r1 >= $atRow) {
                $updated[] = A1Notation::fromRange(
                    $r1 + $count,
                    $c1,
                    $r2 + $count,
                    $c2,
                );
                continue;
            }

            if ($r1 < $atRow && $r2 >= $atRow) {
                $updated[] = A1Notation::fromRange($r1, $c1, $r2 + $count, $c2);
            }
        }

        $layout['merged_cells'] = $updated;
        $sheet->update(['layout' => $layout]);
    }

    private function adjustMergedColumnsInsert(Sheet $sheet, int $atCol, int $count): void
    {
        $layout = $sheet->layout ?? [];
        $merged = $layout['merged_cells'] ?? [];
        $updated = [];

        foreach ($merged as $rangeA1) {
            $parsed = A1Notation::parseRange($rangeA1);
            $r1 = $parsed['start_row'];
            $r2 = $parsed['end_row'];
            $c1 = $parsed['start_col'];
            $c2 = $parsed['end_col'];

            if ($c2 < $atCol) {
                $updated[] = $rangeA1;
                continue;
            }

            if ($c1 >= $atCol) {
                $updated[] = A1Notation::fromRange(
                    $r1,
                    $c1 + $count,
                    $r2,
                    $c2 + $count,
                );
                continue;
            }

            if ($c1 < $atCol && $c2 >= $atCol) {
                $updated[] = A1Notation::fromRange($r1, $c1, $r2, $c2 + $count);
            }
        }

        $layout['merged_cells'] = $updated;
        $sheet->update(['layout' => $layout]);
    }
}
