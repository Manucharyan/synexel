<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Events\ChartChanged;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    public function __construct(private readonly WorkbookService $workbookService) {}

    public function index(Request $request, string $workbookId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);

        return response()->json(['data' => $workbook->charts]);
    }

    public function store(Request $request, string $workbookId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'definition' => ['required', 'array'],
            'definition.type' => ['required', 'string'],
            'definition.range' => ['required', 'string'],
            'definition.series' => ['nullable', 'array'],
        ]);

        $chart = $workbook->charts()->create($data);
        event(new ChartChanged($workbook, $chart, 'created'));

        return response()->json(['data' => $chart], 201);
    }

    public function update(Request $request, string $workbookId, string $id): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $chart = $workbook->charts()->where('id', $id)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'definition' => ['sometimes', 'array'],
        ]);

        $chart->update($data);
        event(new ChartChanged($workbook, $chart->fresh(), 'updated'));

        return response()->json(['data' => $chart->fresh()]);
    }

    public function destroy(Request $request, string $workbookId, string $id): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $chart = $workbook->charts()->where('id', $id)->firstOrFail();

        event(new ChartChanged($workbook, $chart, 'deleted'));
        $chart->delete();

        return response()->json(['message' => 'Chart deleted.']);
    }
}
