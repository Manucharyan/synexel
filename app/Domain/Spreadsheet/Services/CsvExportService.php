<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\Events\CsvExported;
use App\Domain\Spreadsheet\Models\Cell;
use App\Domain\Spreadsheet\Models\Workbook;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    public function export(Workbook $workbook, ?string $sheetId = null, string $delimiter = ','): StreamedResponse
    {
        $sheet = $sheetId
            ? $workbook->sheets()->where('id', $sheetId)->firstOrFail()
            : $workbook->sheets()->orderBy('index')->firstOrFail();

        $cells = Cell::query()->where('sheet_id', $sheet->id)->get();
        $maxRow = $cells->max('row') ?? 0;
        $maxCol = $cells->max('col') ?? 0;

        $grid = [];
        foreach ($cells as $cell) {
            $grid[$cell->row][$cell->col] = $cell->displayValue() ?? '';
        }

        event(new CsvExported($workbook, $sheet->name));

        $filename = $workbook->name.'-'.$sheet->name.'.csv';

        return response()->streamDownload(function () use ($grid, $maxRow, $maxCol, $delimiter) {
            $handle = fopen('php://output', 'w');

            for ($row = 1; $row <= $maxRow; $row++) {
                $line = [];
                for ($col = 1; $col <= $maxCol; $col++) {
                    $line[] = $grid[$row][$col] ?? '';
                }
                fputcsv($handle, $line, $delimiter);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
