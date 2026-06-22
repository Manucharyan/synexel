<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Models\CellChange;
use App\Domain\Spreadsheet\Services\CellBatchService;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationController extends Controller
{
    public function __construct(
        private readonly CellBatchService $cellBatchService,
        private readonly WorkbookService $workbookService,
    ) {}

    public function revert(Request $request, string $operationId): JsonResponse
    {
        $change = CellChange::query()
            ->where('operation_id', $operationId)
            ->firstOrFail();

        $this->workbookService->findForUser($request->user(), $change->workbook_id);

        $result = $this->cellBatchService->revert($operationId);

        return response()->json(['data' => $result]);
    }
}
