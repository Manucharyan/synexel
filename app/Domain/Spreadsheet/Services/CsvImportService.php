<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\Enums\CellValueType;
use App\Domain\Spreadsheet\Events\CsvImported;
use App\Domain\Spreadsheet\Models\Cell;
use App\Domain\Spreadsheet\Models\Sheet;
use App\Domain\Spreadsheet\Models\Workbook;
use App\Models\User;

class CsvImportService
{
    public function __construct(
        private readonly WorkbookService $workbookService,
    ) {}

    public function importWorkbook(User $user, string $filePath, ?string $name = null, string $delimiter = ',', bool $dispatchEvent = true): Workbook
    {
        $workbookName = $name ?? pathinfo($filePath, PATHINFO_FILENAME);
        $workbook = $this->workbookService->create($user, $workbookName, ['imported_from' => 'csv']);
        $workbook->sheets()->delete();

        $sheet = $workbook->sheets()->create([
            'name' => 'Sheet1',
            'index' => 0,
            'layout' => [],
        ]);

        $this->importIntoSheet($sheet, $filePath, $delimiter);

        if ($dispatchEvent) {
            event(new CsvImported($workbook->fresh()->load('sheets')));
        }

        return $workbook->fresh()->load('sheets');
    }

    public function importIntoSheet(Sheet $sheet, string $filePath, string $delimiter = ','): int
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Unable to read CSV file.');
        }

        $row = 1;
        $imported = 0;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            foreach ($data as $colIndex => $value) {
                $col = $colIndex + 1;
                if ($value === '' || $value === null) {
                    continue;
                }

                Cell::updateOrCreate(
                    ['sheet_id' => $sheet->id, 'row' => $row, 'col' => $col],
                    [
                        'raw_value' => $value,
                        'formula' => null,
                        'computed_value' => null,
                        'value_type' => is_numeric($value) ? CellValueType::Number : CellValueType::String,
                    ],
                );
                $imported++;
            }
            $row++;
        }

        fclose($handle);

        return $imported;
    }
}
