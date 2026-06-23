<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\Events\GoogleSheetsImported;
use App\Domain\Spreadsheet\Models\Workbook;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleSheetsImportService
{
    public function __construct(
        private readonly CsvImportService $csvImportService,
    ) {}

    public function import(User $user, string $urlOrId, ?string $name = null, ?string $gid = null): Workbook
    {
        $spreadsheetId = $this->extractSpreadsheetId($urlOrId);
        $exportUrl = $this->buildExportUrl($spreadsheetId, $gid);

        $response = Http::timeout(30)->get($exportUrl);

        if (! $response->successful()) {
            throw new \RuntimeException('Unable to fetch Google Sheet. Ensure the sheet is published or publicly accessible.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'gsheet_');
        file_put_contents($tempPath, $response->body());

        try {
            $workbook = $this->csvImportService->importWorkbook(
                $user,
                $tempPath,
                $name ?? 'Google Sheet '.$spreadsheetId,
                ',',
                false,
            );

            $workbook->update([
                'metadata' => array_merge($workbook->metadata ?? [], [
                    'imported_from' => 'google_sheets',
                    'google_spreadsheet_id' => $spreadsheetId,
                    'google_gid' => $gid,
                ]),
            ]);

            event(new GoogleSheetsImported($workbook->fresh()->load('sheets'), $spreadsheetId));

            return $workbook->fresh()->load('sheets');
        } finally {
            @unlink($tempPath);
        }
    }

    private function extractSpreadsheetId(string $urlOrId): string
    {
        if (Str::isUuid($urlOrId) || preg_match('/^[a-zA-Z0-9_-]{20,}$/', $urlOrId)) {
            return $urlOrId;
        }

        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $urlOrId, $matches)) {
            return $matches[1];
        }

        throw new \InvalidArgumentException('Invalid Google Sheets URL or spreadsheet ID.');
    }

    private function buildExportUrl(string $spreadsheetId, ?string $gid): string
    {
        $url = 'https://docs.google.com/spreadsheets/d/'.$spreadsheetId.'/export?format=csv';

        if ($gid !== null) {
            $url .= '&gid='.$gid;
        }

        return $url;
    }
}
