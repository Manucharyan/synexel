<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Http\Controllers\Controller;
use App\Http\Resources\SheetResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SheetController extends Controller
{
    public function __construct(private readonly WorkbookService $workbookService) {}

    public function store(Request $request, string $workbookId): SheetResource
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'index' => ['nullable', 'integer', 'min:0'],
        ]);

        $sheet = $this->workbookService->createSheet(
            $workbook,
            $data['name'],
            $data['index'] ?? null
        );

        return new SheetResource($sheet);
    }

    public function show(Request $request, string $workbookId, string $sheetId): SheetResource
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        return new SheetResource($sheet);
    }

    public function update(Request $request, string $workbookId, string $sheetId): SheetResource
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'index' => ['sometimes', 'integer', 'min:0'],
            'layout' => ['sometimes', 'array'],
        ]);

        return new SheetResource($this->workbookService->updateSheet($sheet, $data));
    }

    public function destroy(Request $request, string $workbookId, string $sheetId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);
        $sheet = $workbook->sheets()->where('id', $sheetId)->firstOrFail();

        $this->workbookService->deleteSheet($sheet);

        return response()->json(['message' => 'Sheet deleted.']);
    }
}
