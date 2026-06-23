<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Enums\SharePermission;
use App\Domain\Spreadsheet\Services\CellBatchService;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Http\Controllers\Concerns\ChecksSpreadsheetAccess;
use App\Http\Controllers\Controller;
use App\Services\UserCapabilitiesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CellController extends Controller
{
    use ChecksSpreadsheetAccess;

    public function __construct(
        private readonly WorkbookService $workbookService,
        private readonly CellBatchService $cellBatchService,
        private readonly UserCapabilitiesService $capabilities,
    ) {}

    public function index(Request $request, string $workbookId, string $sheetId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId, SharePermission::Read);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $data = $request->validate([
            'range' => ['required', 'string'],
            'include_hidden' => ['nullable', 'boolean'],
        ]);

        $cells = $this->cellBatchService->readRange(
            $sheet,
            $data['range'],
            (bool) ($data['include_hidden'] ?? false)
        );

        return response()->json(['data' => $cells]);
    }

    public function update(Request $request, string $workbookId, string $sheetId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $data = $request->validate([
            'operation_id' => ['nullable', 'string', 'max:64'],
            'updates' => ['required', 'array', 'min:1'],
            'updates.*.row' => ['required', 'integer', 'min:1'],
            'updates.*.col' => ['required', 'integer', 'min:1'],
            'updates.*.value' => ['nullable'],
            'updates.*.formula' => ['nullable', 'string'],
            'updates.*.style' => ['nullable', 'array'],
            'updates.*.clear' => ['nullable', 'boolean'],
            'recalculate' => ['nullable', 'boolean'],
        ]);

        $this->capabilities->assertUpdatesAllowed($request->user(), $data['updates']);

        $result = $this->cellBatchService->batchUpdate(
            $sheet,
            $data['updates'],
            $data['operation_id'] ?? null,
            $data['recalculate'] ?? true,
            user: $request->user(),
        );

        return response()->json(['data' => $result]);
    }

    public function clear(Request $request, string $workbookId, string $sheetId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $data = $request->validate([
            'range' => ['required', 'string'],
            'operation_id' => ['nullable', 'string', 'max:64'],
        ]);

        $this->capabilities->assertCanDelete($request->user(), 'clear range');

        $result = $this->cellBatchService->clearRange(
            $sheet,
            $data['range'],
            $data['operation_id'] ?? null,
            $request->user(),
        );

        return response()->json(['data' => $result]);
    }

    public function copy(Request $request, string $workbookId, string $sheetId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $data = $request->validate([
            'source_range' => ['required', 'string'],
            'target_cell' => ['required', 'string'],
            'values' => ['nullable', 'boolean'],
            'formulas' => ['nullable', 'boolean'],
            'formats' => ['nullable', 'boolean'],
        ]);

        $result = $this->cellBatchService->copyRange(
            $sheet,
            $data['source_range'],
            $data['target_cell'],
            $data['values'] ?? true,
            $data['formulas'] ?? true,
            $data['formats'] ?? true,
            $request->user(),
        );

        return response()->json(['data' => $result]);
    }
}
