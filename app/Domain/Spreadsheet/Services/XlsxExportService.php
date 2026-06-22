<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\Events\WorkbookExported;
use App\Domain\Spreadsheet\Models\Cell;
use App\Domain\Spreadsheet\Models\Workbook;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class XlsxExportService
{
    public function export(Workbook $workbook): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($workbook->sheets()->orderBy('index')->get() as $index => $sheet) {
            $worksheet = $spreadsheet->createSheet($index);
            $worksheet->setTitle($sheet->name);

            $cells = Cell::query()->where('sheet_id', $sheet->id)->get();

            foreach ($cells as $cell) {
                $coordinate = [$cell->col, $cell->row];

                if ($cell->formula) {
                    $worksheet->setCellValue($coordinate, $cell->formula);
                } else {
                    $worksheet->setCellValue($coordinate, $cell->raw_value);
                }

                if ($cell->style) {
                    if (! empty($cell->style['bold'])) {
                        $worksheet->getStyle($coordinate)->getFont()->setBold(true);
                    }

                    if (! empty($cell->style['bg'])) {
                        $rgb = ltrim($cell->style['bg'], '#');
                        $worksheet->getStyle($coordinate)->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB($rgb);
                    }

                    if (! empty($cell->style['color'])) {
                        $rgb = ltrim($cell->style['color'], '#');
                        $worksheet->getStyle($coordinate)->getFont()->getColor()->setRGB($rgb);
                    }
                }
            }

            foreach ($sheet->layout['merged_cells'] ?? [] as $mergeRange) {
                $worksheet->mergeCells($mergeRange);
            }
        }

        foreach ($workbook->namedRanges as $namedRange) {
            $spreadsheet->addNamedRange(
                new \PhpOffice\PhpSpreadsheet\NamedRange(
                    $namedRange->name,
                    $spreadsheet->getSheetByName($namedRange->sheet_name) ?? $spreadsheet->getSheet(0),
                    $namedRange->range_a1
                )
            );
        }

        $tempPath = storage_path('app/exports/'.uniqid('workbook_', true).'.xlsx');
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($tempPath);

        event(new WorkbookExported($workbook));

        return $tempPath;
    }
}
