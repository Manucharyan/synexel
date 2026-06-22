<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Events\ConditionalFormatChanged;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConditionalFormatController extends Controller
{
    public function __construct(private readonly WorkbookService $workbookService) {}

    public function index(Request $request, string $workbookId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);

        return response()->json(['data' => $workbook->conditionalFormats]);
    }

    public function store(Request $request, string $workbookId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);

        $data = $request->validate([
            'sheet_name' => ['required', 'string', 'max:255'],
            'range_a1' => ['required', 'string'],
            'rule_type' => ['required', 'string', 'in:greater_than,less_than,equal,contains,not_empty'],
            'formula' => ['nullable', 'string'],
            'style' => ['required', 'array'],
            'priority' => ['nullable', 'integer', 'min:0'],
        ]);

        $format = $workbook->conditionalFormats()->create($data);
        event(new ConditionalFormatChanged($workbook, $format, 'created'));

        return response()->json(['data' => $format], 201);
    }

    public function update(Request $request, string $workbookId, string $id): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $format = $workbook->conditionalFormats()->where('id', $id)->firstOrFail();

        $data = $request->validate([
            'sheet_name' => ['sometimes', 'string', 'max:255'],
            'range_a1' => ['sometimes', 'string'],
            'rule_type' => ['sometimes', 'string', 'in:greater_than,less_than,equal,contains,not_empty'],
            'formula' => ['nullable', 'string'],
            'style' => ['sometimes', 'array'],
            'priority' => ['sometimes', 'integer', 'min:0'],
        ]);

        $format->update($data);
        event(new ConditionalFormatChanged($workbook, $format->fresh(), 'updated'));

        return response()->json(['data' => $format->fresh()]);
    }

    public function destroy(Request $request, string $workbookId, string $id): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $format = $workbook->conditionalFormats()->where('id', $id)->firstOrFail();

        event(new ConditionalFormatChanged($workbook, $format, 'deleted'));
        $format->delete();

        return response()->json(['message' => 'Conditional format deleted.']);
    }
}
