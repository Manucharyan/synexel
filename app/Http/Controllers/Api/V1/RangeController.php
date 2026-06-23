<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Services\SheetStructureService;
use App\Domain\Spreadsheet\Services\SortFilterService;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Http\Controllers\Concerns\ChecksSpreadsheetAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\SheetResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RangeController extends Controller
{
    use ChecksSpreadsheetAccess;

    public function __construct(
        private readonly WorkbookService $workbookService,
        private readonly SortFilterService $sortFilterService,
        private readonly SheetStructureService $sheetStructureService,
    ) {}

    public function sort(Request $request, string $workbookId, string $sheetId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $data = $request->validate([
            'range' => ['required', 'string'],
            'column' => ['required', 'integer', 'min:1'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $result = $this->sortFilterService->sort(
            $sheet,
            $data['range'],
            $data['column'],
            $data['order'] ?? 'asc',
        );

        return response()->json(['data' => $result]);
    }

    public function filter(Request $request, string $workbookId, string $sheetId): SheetResource
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $data = $request->validate([
            'hide_rows' => ['nullable', 'array'],
            'hide_rows.*' => ['integer', 'min:1'],
            'criteria' => ['nullable', 'array'],
        ]);

        $sheet = $this->sortFilterService->applyFilter($sheet, $data);

        return new SheetResource($sheet);
    }

    public function deleteRows(Request $request, string $workbookId, string $sheetId): JsonResponse
    {
        $this->assertCanDeleteSpreadsheetData($request);

        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $data = $request->validate([
            'start_row' => ['required', 'integer', 'min:1'],
            'end_row' => ['required', 'integer', 'min:1', 'gte:start_row'],
        ]);

        $result = $this->sheetStructureService->deleteRows(
            $sheet,
            $data['start_row'],
            $data['end_row'],
        );

        return response()->json(['data' => $result]);
    }

    public function deleteColumns(Request $request, string $workbookId, string $sheetId): JsonResponse
    {
        $this->assertCanDeleteSpreadsheetData($request);

        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $data = $request->validate([
            'start_col' => ['required', 'integer', 'min:1'],
            'end_col' => ['required', 'integer', 'min:1', 'gte:start_col'],
        ]);

        $result = $this->sheetStructureService->deleteColumns(
            $sheet,
            $data['start_col'],
            $data['end_col'],
        );

        return response()->json(['data' => $result]);
    }

    public function insertRows(Request $request, string $workbookId, string $sheetId): JsonResponse
    {
        $this->assertCanAddSpreadsheetData($request);

        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $data = $request->validate([
            'at_row' => ['required', 'integer', 'min:1'],
            'count' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $this->sheetStructureService->insertRows(
            $sheet,
            $data['at_row'],
            $data['count'] ?? 1,
        );

        return response()->json(['data' => $result]);
    }

    public function insertColumns(Request $request, string $workbookId, string $sheetId): JsonResponse
    {
        $this->assertCanAddSpreadsheetData($request);

        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $data = $request->validate([
            'at_col' => ['required', 'integer', 'min:1'],
            'count' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $this->sheetStructureService->insertColumns(
            $sheet,
            $data['at_col'],
            $data['count'] ?? 1,
        );

        return response()->json(['data' => $result]);
    }
}
