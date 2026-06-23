<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Enums\AuditAction;
use App\Domain\Spreadsheet\Enums\SharePermission;
use App\Domain\Spreadsheet\Services\CsvExportService;
use App\Domain\Spreadsheet\Services\CsvImportService;
use App\Domain\Spreadsheet\Services\GoogleSheetsImportService;
use App\Domain\Spreadsheet\Services\WorkbookAccessService;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Domain\Spreadsheet\Services\XlsxExportService;
use App\Domain\Spreadsheet\Services\XlsxImportService;
use App\Domain\Spreadsheet\Services\AuditLogService;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkbookResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends Controller
{
    public function __construct(
        private readonly WorkbookService $workbookService,
        private readonly WorkbookAccessService $accessService,
        private readonly XlsxImportService $importService,
        private readonly XlsxExportService $exportService,
        private readonly CsvImportService $csvImportService,
        private readonly CsvExportService $csvExportService,
        private readonly GoogleSheetsImportService $googleSheetsImportService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function import(Request $request): WorkbookResource
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $workbook = $this->importService->import(
            $request->user(),
            $request->file('file')->getRealPath(),
            $request->input('name'),
        );

        return new WorkbookResource($workbook);
    }

    public function importCsv(Request $request): WorkbookResource
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            'name' => ['nullable', 'string', 'max:255'],
            'delimiter' => ['nullable', 'string', 'max:1'],
        ]);

        $workbook = $this->csvImportService->importWorkbook(
            $request->user(),
            $request->file('file')->getRealPath(),
            $request->input('name'),
            $request->input('delimiter', ','),
        );

        return new WorkbookResource($workbook);
    }

    public function importGoogleSheet(Request $request): WorkbookResource
    {
        $data = $request->validate([
            'url' => ['required_without:spreadsheet_id', 'nullable', 'string', 'max:2048'],
            'spreadsheet_id' => ['required_without:url', 'nullable', 'string', 'max:128'],
            'gid' => ['nullable', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $workbook = $this->googleSheetsImportService->import(
            $request->user(),
            $data['url'] ?? $data['spreadsheet_id'],
            $data['name'] ?? null,
            $data['gid'] ?? null,
        );

        return new WorkbookResource($workbook);
    }

    public function export(Request $request, string $workbookId): BinaryFileResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId, SharePermission::Read);
        $workbook->load(['sheets', 'namedRanges']);

        $path = $this->exportService->export($workbook);

        return response()->download($path, $workbook->name.'.xlsx')->deleteFileAfterSend();
    }

    public function exportCsv(Request $request, string $workbookId): StreamedResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId, SharePermission::Read);
        $workbook->load('sheets');

        return $this->csvExportService->export(
            $workbook,
            $request->query('sheet_id'),
            $request->query('delimiter', ','),
        );
    }

    public function importSheet(Request $request, string $workbookId, string $sheetId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:20480'],
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType();
        $path = $file->getRealPath();

        if (str_contains($mime, 'csv') || str_ends_with($file->getClientOriginalName(), '.csv')) {
            $count = $this->csvImportService->importIntoSheet($sheet, $path, $request->input('delimiter', ','));
            $this->auditLogService->record(
                AuditAction::CsvImported,
                'Imported CSV into sheet "'.$sheet->name.'"',
                $workbook,
                $sheet,
                details: ['cells' => $count],
                resourceType: 'csv',
            );
        } else {
            $this->importService->importIntoSheet($sheet->id, $path);
        }

        return response()->json(['message' => 'Sheet data imported.']);
    }
}
