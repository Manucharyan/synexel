<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\DTOs\RangeRef;
use App\Domain\Spreadsheet\Models\Cell;
use App\Domain\Spreadsheet\Models\ConditionalFormat;
use App\Domain\Spreadsheet\Models\Sheet;

class ConditionalFormatService
{
    public function getForSheet(Sheet $sheet): array
    {
        return ConditionalFormat::query()
            ->where('workbook_id', $sheet->workbook_id)
            ->where('sheet_name', $sheet->name)
            ->orderBy('priority')
            ->get()
            ->all();
    }

    public function resolveStyle(Cell $cell, string $sheetName, array $rules): ?array
    {
        foreach ($rules as $rule) {
            if (! $this->cellInRange($cell, $rule->range_a1)) {
                continue;
            }

            if ($this->matchesRule($cell, $rule)) {
                return $rule->style;
            }
        }

        return null;
    }

    private function cellInRange(Cell $cell, string $rangeA1): bool
    {
        $ref = RangeRef::fromA1($rangeA1);

        return $cell->row >= $ref->startRow
            && $cell->row <= $ref->endRow
            && $cell->col >= $ref->startCol
            && $cell->col <= $ref->endCol;
    }

    private function matchesRule(Cell $cell, ConditionalFormat $rule): bool
    {
        $value = $cell->displayValue();

        return match ($rule->rule_type) {
            'greater_than' => is_numeric($value) && is_numeric($rule->formula) && (float) $value > (float) $rule->formula,
            'less_than' => is_numeric($value) && is_numeric($rule->formula) && (float) $value < (float) $rule->formula,
            'equal' => (string) $value === (string) $rule->formula,
            'contains' => str_contains((string) $value, (string) $rule->formula),
            'not_empty' => $value !== null && $value !== '',
            default => false,
        };
    }
}
