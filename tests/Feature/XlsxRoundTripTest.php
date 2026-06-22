<?php

namespace Tests\Feature;

use App\Domain\Spreadsheet\Services\XlsxExportService;
use App\Domain\Spreadsheet\Services\XlsxImportService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class XlsxRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_import_export_round_trip(): void
    {
        $user = User::factory()->create();
        $tempPath = storage_path('app/test_roundtrip.xlsx');

        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '10');
        $sheet->setCellValue('A2', '=A1*3');
        (new Xlsx($spreadsheet))->save($tempPath);

        $importService = app(XlsxImportService::class);
        $workbook = $importService->import($user, $tempPath, 'Round Trip');

        $dbSheet = $workbook->sheets->first();
        $cellA1 = $dbSheet->cells()->where('row', 1)->where('col', 1)->first();
        $cellA2 = $dbSheet->cells()->where('row', 2)->where('col', 1)->first();

        $this->assertEquals('10', $cellA1->raw_value);
        $this->assertEquals('=A1*3', $cellA2->formula);

        $exportPath = app(XlsxExportService::class)->export($workbook->fresh(['sheets', 'namedRanges']));
        $this->assertFileExists($exportPath);

        $reloaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($exportPath);
        $this->assertEquals('10', $reloaded->getActiveSheet()->getCell('A1')->getValue());
        $this->assertEquals('=A1*3', $reloaded->getActiveSheet()->getCell('A2')->getValue());

        @unlink($tempPath);
        @unlink($exportPath);
    }
}
