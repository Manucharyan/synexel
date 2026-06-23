<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\DTOs\A1Notation;
use App\Domain\Spreadsheet\DTOs\CellUpdate;
use App\Domain\Spreadsheet\DTOs\RangeRef;
use App\Domain\Spreadsheet\Enums\CellValueType;
use App\Domain\Spreadsheet\Events\CellsUpdated;
use App\Domain\Spreadsheet\Events\OperationReverted;
use App\Domain\Spreadsheet\Models\Cell;
use App\Domain\Spreadsheet\Models\CellChange;
use App\Domain\Spreadsheet\Models\Sheet;
use App\Models\User;
use App\Services\SpreadsheetSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CellBatchService
{
    public function __construct(
        private readonly FormulaEvaluationService $formulaService,
        private readonly ConditionalFormatService $conditionalFormatService,
        private readonly SpreadsheetSettingsService $spreadsheetSettings,
    ) {}

    public function readRange(Sheet $sheet, string $range, bool $includeHidden = false): array
    {
        $ref = RangeRef::fromA1($range);
        $hiddenRows = $includeHidden ? [] : $sheet->hiddenRows();

        $cells = Cell::query()
            ->where('sheet_id', $sheet->id)
            ->whereBetween('row', [$ref->startRow, $ref->endRow])
            ->whereBetween('col', [$ref->startCol, $ref->endCol])
            ->get();

        $conditionalFormats = $this->conditionalFormatService->getForSheet($sheet);

        return $cells
            ->filter(fn (Cell $cell) => $includeHidden || ! in_array($cell->row, $hiddenRows, true))
            ->map(function (Cell $cell) use ($conditionalFormats, $sheet) {
                $effectiveStyle = $this->conditionalFormatService->resolveStyle(
                    $cell,
                    $sheet->name,
                    $conditionalFormats
                );

                return [
                    'row' => $cell->row,
                    'col' => $cell->col,
                    'address' => A1Notation::fromCoordinates($cell->row, $cell->col),
                    'value' => $cell->raw_value,
                    'formula' => $cell->formula,
                    'computed' => $cell->displayValue(),
                    'style' => $this->normalizeStyle($effectiveStyle ?? $cell->style),
                    'value_type' => $cell->value_type?->value,
                ];
            })
            ->values()
            ->all();
    }

    public function batchUpdate(
        Sheet $sheet,
        array $updates,
        ?string $operationId = null,
        bool $recalculate = true,
        ?string $auditSource = null,
        ?User $user = null,
    ): array {
        $operationId ??= 'op_'.Str::ulid();
        $parsedUpdates = array_map(fn ($u) => CellUpdate::fromArray($u), $updates);

        if (count($parsedUpdates) > config('spreadsheet.max_cells_per_request')) {
            throw new \InvalidArgumentException('Too many cells in one request.');
        }

        $this->spreadsheetSettings->assertCellUpdatesAllowed($user, $sheet, $parsedUpdates);

        $changes = [];

        DB::transaction(function () use ($sheet, $parsedUpdates, $operationId, $recalculate, &$changes) {
            foreach ($parsedUpdates as $update) {
                $existing = Cell::query()
                    ->where('sheet_id', $sheet->id)
                    ->where('row', $update->row)
                    ->where('col', $update->col)
                    ->first();

                $before = $existing?->toSnapshot();

                if ($update->clear) {
                    if ($existing) {
                        $existing->delete();
                        $changes[] = $this->logChange($operationId, $sheet, $update->row, $update->col, $before, null);
                    }
                    continue;
                }

                $attributes = $this->buildAttributes($update, $existing);

                if ($existing) {
                    $existing->update($attributes);
                    $cell = $existing->fresh();
                } else {
                    $cell = Cell::create(array_merge([
                        'sheet_id' => $sheet->id,
                        'row' => $update->row,
                        'col' => $update->col,
                    ], $attributes));
                }

                $changes[] = $this->logChange($operationId, $sheet, $update->row, $update->col, $before, $cell->toSnapshot());
            }

            if ($recalculate) {
                $this->formulaService->recalculateSheet($sheet->fresh());
            }
        });

        event(new CellsUpdated($sheet->workbook, $sheet, $operationId, $changes, $auditSource));

        return [
            'operation_id' => $operationId,
            'updated' => count($changes),
            'changes' => $changes,
        ];
    }

    public function clearRange(Sheet $sheet, string $range, ?string $operationId = null, ?User $user = null): array
    {
        $this->spreadsheetSettings->assertCanDelete($user);

        $operationId ??= 'op_'.Str::ulid();
        $ref = RangeRef::fromA1($range);

        if ($ref->cellCount() > config('spreadsheet.max_cells_per_request')) {
            throw new \InvalidArgumentException('Range too large to clear.');
        }

        $cells = Cell::query()
            ->where('sheet_id', $sheet->id)
            ->whereBetween('row', [$ref->startRow, $ref->endRow])
            ->whereBetween('col', [$ref->startCol, $ref->endCol])
            ->get();

        $changes = [];

        DB::transaction(function () use ($cells, $sheet, $operationId, &$changes) {
            foreach ($cells as $cell) {
                $before = $cell->toSnapshot();
                $cell->delete();
                $changes[] = $this->logChange($operationId, $sheet, $cell->row, $cell->col, $before, null);
            }
        });

        event(new RangeCleared($sheet->workbook, $sheet, $operationId, $range, $changes));

        return ['operation_id' => $operationId, 'cleared' => count($changes)];
    }

    public function copyRange(
        Sheet $sheet,
        string $sourceRange,
        string $targetCell,
        bool $values = true,
        bool $formulas = true,
        bool $formats = true,
        ?User $user = null,
    ): array {
        $source = RangeRef::fromA1($sourceRange);
        $target = A1Notation::toCoordinates($targetCell);
        $rowOffset = $target['row'] - $source->startRow;
        $colOffset = $target['col'] - $source->startCol;

        $cells = Cell::query()
            ->where('sheet_id', $sheet->id)
            ->whereBetween('row', [$source->startRow, $source->endRow])
            ->whereBetween('col', [$source->startCol, $source->endCol])
            ->get();

        $updates = [];

        foreach ($cells as $cell) {
            $update = ['row' => $cell->row + $rowOffset, 'col' => $cell->col + $colOffset];

            if ($values && ! $cell->formula) {
                $update['value'] = $cell->raw_value;
            }

            if ($formulas && $cell->formula) {
                $update['formula'] = $this->offsetFormula($cell->formula, $rowOffset, $colOffset);
            }

            if ($formats && $cell->style) {
                $update['style'] = $cell->style;
            }

            $updates[] = $update;
        }

        return $this->batchUpdate($sheet, $updates, user: $user);
    }

    public function revert(string $operationId, ?User $user = null): array
    {
        $this->spreadsheetSettings->assertCanAdd($user);
        $this->spreadsheetSettings->assertCanDelete($user);

        $changes = CellChange::query()
            ->where('operation_id', $operationId)
            ->where('reverted', false)
            ->orderByDesc('created_at')
            ->get();

        if ($changes->isEmpty()) {
            throw new \InvalidArgumentException('Operation not found or already reverted.');
        }

        $sheet = Sheet::findOrFail($changes->first()->sheet_id);
        $reverted = 0;

        DB::transaction(function () use ($changes, $sheet, &$reverted) {
            foreach ($changes as $change) {
                $cell = Cell::query()
                    ->where('sheet_id', $change->sheet_id)
                    ->where('row', $change->row)
                    ->where('col', $change->col)
                    ->first();

                if ($change->before === null) {
                    $cell?->delete();
                } else {
                    $data = $change->before;
                    Cell::updateOrCreate(
                        [
                            'sheet_id' => $change->sheet_id,
                            'row' => $change->row,
                            'col' => $change->col,
                        ],
                        [
                            'raw_value' => $data['raw_value'] ?? null,
                            'formula' => $data['formula'] ?? null,
                            'computed_value' => $data['computed_value'] ?? null,
                            'style' => $data['style'] ?? null,
                            'value_type' => $data['value_type'] ?? CellValueType::String->value,
                        ]
                    );
                }

                $change->update(['reverted' => true]);
                $reverted++;
            }

            $this->formulaService->recalculateSheet($sheet->fresh());
        });

        event(new OperationReverted($sheet->workbook, $operationId, $reverted));

        return ['operation_id' => $operationId, 'reverted' => $reverted];
    }

    private function buildAttributes(CellUpdate $update, ?Cell $existing): array
    {
        $attributes = [];

        if ($update->formula !== null) {
            $formula = $update->formula;
            if (! str_starts_with($formula, '=')) {
                $formula = '='.$formula;
            }

            $attributes['formula'] = $formula;
            $attributes['raw_value'] = null;
            $attributes['value_type'] = CellValueType::Formula;
        } elseif ($update->value !== null) {
            $attributes['raw_value'] = $update->value;
            $attributes['formula'] = null;
            $attributes['computed_value'] = null;
            $attributes['value_type'] = $this->detectValueType($update->value);
        }

        if ($update->style !== null) {
            $attributes['style'] = array_merge($existing?->style ?? [], $update->style);
        } elseif ($existing) {
            $attributes['style'] = $existing->style;
        }

        /* ConvertEmptyStringsToNull middleware converts value:'' → null, so
           when styling a brand-new empty cell both formula and value are null.
           We need at least raw_value='' so Cell::create() works correctly. */
        if (!$existing && !isset($attributes['raw_value']) && !isset($attributes['formula'])) {
            $attributes['raw_value'] = '';
            $attributes['value_type'] = CellValueType::String;
        }

        return $attributes;
    }

    private function normalizeStyle(?array $style): ?array
    {
        if ($style === null || $style === []) {
            return null;
        }

        return $style;
    }

    private function detectValueType(string $value): CellValueType
    {
        if (is_numeric($value)) {
            return CellValueType::Number;
        }

        if (in_array(strtoupper($value), ['TRUE', 'FALSE'], true)) {
            return CellValueType::Boolean;
        }

        return CellValueType::String;
    }

    private function logChange(
        string $operationId,
        Sheet $sheet,
        int $row,
        int $col,
        ?array $before,
        ?array $after,
    ): array {
        CellChange::create([
            'operation_id' => $operationId,
            'workbook_id' => $sheet->workbook_id,
            'sheet_id' => $sheet->id,
            'row' => $row,
            'col' => $col,
            'before' => $before,
            'after' => $after,
            'created_at' => now(),
        ]);

        return [
            'row' => $row,
            'col' => $col,
            'before' => $before,
            'after' => $after,
        ];
    }

    private function offsetFormula(string $formula, int $rowOffset, int $colOffset): string
    {
        return preg_replace_callback('/(\$?)([A-Z]+)(\$?)(\d+)/', function ($m) use ($rowOffset, $colOffset) {
            $colFixed = $m[1] === '$';
            $rowFixed = $m[3] === '$';

            $coords = A1Notation::toCoordinates($m[0]);
            $newRow = $rowFixed ? $coords['row'] : $coords['row'] + $rowOffset;
            $newCol = $colFixed ? $coords['col'] : $coords['col'] + $colOffset;

            $col = A1Notation::fromCoordinates(1, $newCol);
            $colLetters = preg_replace('/\d+/', '', $col);

            return ($colFixed ? '$' : '').$colLetters.($rowFixed ? '$' : '').$newRow;
        }, $formula) ?? $formula;
    }
}
