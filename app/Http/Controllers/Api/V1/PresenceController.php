<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Enums\SharePermission;
use App\Domain\Spreadsheet\Services\PresenceService;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Domain\Spreadsheet\Services\WorkbookSyncService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function __construct(
        private readonly WorkbookService $workbookService,
        private readonly PresenceService $presenceService,
        private readonly WorkbookSyncService $syncService,
    ) {}

    public function index(Request $request, string $workbookId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId, SharePermission::Read);

        return response()->json([
            'data' => $this->presenceService->list($workbook),
        ]);
    }

    public function heartbeat(Request $request, string $workbookId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId, SharePermission::Read);

        $data = $request->validate([
            'sheet_id' => ['nullable', 'uuid'],
            'row' => ['nullable', 'integer', 'min:1'],
            'col' => ['nullable', 'integer', 'min:1'],
        ]);

        $viewers = $this->presenceService->heartbeat(
            $workbook,
            $request->user(),
            $data['sheet_id'] ?? null,
            $data['row'] ?? null,
            $data['col'] ?? null,
        );

        return response()->json(['data' => $viewers]);
    }

    public function leave(Request $request, string $workbookId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId, SharePermission::Read);
        $viewers = $this->presenceService->leave($workbook, $request->user());

        return response()->json(['data' => $viewers]);
    }

    public function sync(Request $request, string $workbookId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId, SharePermission::Read);

        return response()->json([
            'data' => $this->syncService->changesSince(
                $workbook,
                $request->user(),
                $request->query('since'),
                $request->query('exclude_operation'),
            ),
        ]);
    }
}
