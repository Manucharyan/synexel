<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Domain\Spreadsheet\Services\XlsxExportService;
use App\Domain\Spreadsheet\Services\XlsxImportService;
use App\Http\Controllers\Concerns\ChecksSpreadsheetAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkbookResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportExportController extends Controller
{
    use ChecksSpreadsheetAccess;

    public function __construct(
        private readonly WorkbookService $workbookService,
        private readonly XlsxImportService $importService,
        private readonly XlsxExportService $exportService,
    ) {}

    public function import(Request $request): WorkbookResource
    {
        $this->assertCanAddSpreadsheetData($request);

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

    public function export(Request $request, string $workbookId): BinaryFileResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $workbook->load(['sheets', 'namedRanges']);

        $path = $this->exportService->export($workbook);

        return response()->download($path, $workbook->name.'.xlsx')->deleteFileAfterSend();
    }

    public function importSheet(Request $request, string $workbookId, string $sheetId): JsonResponse
    {
        $this->assertCanAddSpreadsheetData($request);

        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        $this->importService->importIntoSheet($sheet->id, $request->file('file')->getRealPath());

        return response()->json(['message' => 'Sheet data imported.']);
    }
}
