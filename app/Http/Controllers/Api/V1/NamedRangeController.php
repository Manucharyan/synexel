<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Events\NamedRangeChanged;
use App\Domain\Spreadsheet\Models\NamedRange;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NamedRangeController extends Controller
{
    public function __construct(private readonly WorkbookService $workbookService) {}

    public function index(Request $request, string $workbookId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);

        return response()->json(['data' => $workbook->namedRanges]);
    }

    public function store(Request $request, string $workbookId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sheet_name' => ['required', 'string', 'max:255'],
            'range_a1' => ['required', 'string'],
        ]);

        $namedRange = $workbook->namedRanges()->create($data);
        event(new NamedRangeChanged($workbook, $namedRange, 'created'));

        return response()->json(['data' => $namedRange], 201);
    }

    public function update(Request $request, string $workbookId, string $id): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $namedRange = $workbook->namedRanges()->where('id', $id)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sheet_name' => ['sometimes', 'string', 'max:255'],
            'range_a1' => ['sometimes', 'string'],
        ]);

        $namedRange->update($data);
        event(new NamedRangeChanged($workbook, $namedRange->fresh(), 'updated'));

        return response()->json(['data' => $namedRange->fresh()]);
    }

    public function destroy(Request $request, string $workbookId, string $id): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $namedRange = $workbook->namedRanges()->where('id', $id)->firstOrFail();

        event(new NamedRangeChanged($workbook, $namedRange, 'deleted'));
        $namedRange->delete();

        return response()->json(['message' => 'Named range deleted.']);
    }
}
