<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\Enums\CellValueType;
use App\Domain\Spreadsheet\Events\WorkbookImported;
use App\Domain\Spreadsheet\Models\Cell;
use App\Domain\Spreadsheet\Models\NamedRange;
use App\Domain\Spreadsheet\Models\Workbook;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class XlsxImportService
{
    public function __construct(
        private readonly WorkbookService $workbookService,
    ) {}

    public function import(User $user, string $filePath, ?string $name = null): Workbook
    {
        $spreadsheet = IOFactory::load($filePath);
        $workbookName = $name ?? pathinfo($filePath, PATHINFO_FILENAME);

        $workbook = Workbook::create([
            'user_id' => $user->id,
            'name' => $workbookName,
            'metadata' => ['imported_from' => basename($filePath)],
        ]);

        $workbook->sheets()->delete();

        foreach ($spreadsheet->getWorksheetIterator() as $index => $worksheet) {
            $sheet = $workbook->sheets()->create([
                'name' => $worksheet->getTitle(),
                'index' => $index,
                'layout' => [],
            ]);

            $this->importSheet($sheet->id, $worksheet);

            foreach ($worksheet->getMergeCells() as $mergeRange) {
                $layout = $sheet->layout ?? [];
                $layout['merged_cells'][] = $mergeRange;
                $sheet->update(['layout' => $layout]);
            }
        }

        foreach ($spreadsheet->getDefinedNames() as $definedName) {
            $range = (string) $definedName->getValue();
            $parts = explode('!', $range);
            $sheetName = count($parts) > 1 ? trim($parts[0], "'") : $spreadsheet->getSheet(0)->getTitle();
            $rangeA1 = count($parts) > 1 ? $parts[1] : $parts[0];

            NamedRange::create([
                'workbook_id' => $workbook->id,
                'name' => $definedName->getName(),
                'sheet_name' => $sheetName,
                'range_a1' => str_replace('$', '', $rangeA1),
            ]);
        }

        event(new WorkbookImported($workbook));

        return $workbook->load('sheets');
    }

    public function importIntoSheet(string $sheetId, string $filePath): void
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getSheet(0);
        $this->importSheet($sheetId, $worksheet);
    }

    private function importSheet(string $sheetId, Worksheet $worksheet): void
    {
        $highestRow = $worksheet->getHighestDataRow();
        $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestCol; $col++) {
                $cell = $worksheet->getCell([$col, $row]);
                $value = $cell->getValue();
                $formatted = $cell->getFormattedValue();

                if ($value === null && $formatted === '') {
                    continue;
                }

                $isFormula = is_string($value) && str_starts_with($value, '=');

                Cell::create([
                    'sheet_id' => $sheetId,
                    'row' => $row,
                    'col' => $col,
                    'raw_value' => $isFormula ? null : (string) $formatted,
                    'formula' => $isFormula ? $value : null,
                    'computed_value' => $isFormula ? (string) $formatted : null,
                    'value_type' => $isFormula ? CellValueType::Formula : $this->detectType($formatted),
                    'style' => $this->extractStyle($cell),
                ]);
            }
        }
    }

    private function detectType(mixed $value): CellValueType
    {
        if (is_numeric($value)) {
            return CellValueType::Number;
        }

        return CellValueType::String;
    }

    private function extractStyle(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): ?array
    {
        $style = [];

        if ($cell->getStyle()->getFont()->getBold()) {
            $style['bold'] = true;
        }

        $fill = $cell->getStyle()->getFill()->getStartColor()->getRGB();
        if ($fill && $fill !== 'FFFFFF') {
            $style['bg'] = '#'.$fill;
        }

        $fontColor = $cell->getStyle()->getFont()->getColor()->getRGB();
        if ($fontColor) {
            $style['color'] = '#'.$fontColor;
        }

        return $style ?: null;
    }
}
