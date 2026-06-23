<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Enums\AuditAction;
use App\Domain\Spreadsheet\Enums\SharePermission;
use App\Domain\Spreadsheet\Services\AuditLogService;
use App\Domain\Spreadsheet\Services\WorkbookAccessService;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkbookResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkbookController extends Controller
{
    public function __construct(
        private readonly WorkbookService $workbookService,
        private readonly WorkbookAccessService $accessService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return WorkbookResource::collection($this->workbookService->listForUser($request->user()));
    }

    public function store(Request $request): WorkbookResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $workbook = $this->workbookService->create(
            $request->user(),
            $data['name'],
            $data['metadata'] ?? []
        );

        return new WorkbookResource($workbook);
    }

    public function show(Request $request, string $id): WorkbookResource
    {
        $workbook = $this->workbookService->findForUser($request->user(), $id, SharePermission::Read);
        $workbook->load('sheets');

        return new WorkbookResource($workbook);
    }

    public function update(Request $request, string $id): WorkbookResource
    {
        $workbook = $this->workbookService->findForUser($request->user(), $id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
        ]);

        return new WorkbookResource($this->workbookService->update($workbook, $data));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $id);

        if (! $this->accessService->isOwner($request->user(), $workbook)) {
            $this->auditLogService->recordDenied(
                AuditAction::WorkbookDeleted,
                'Denied delete attempt on "'.$workbook->name.'"',
                $workbook,
                details: ['reason' => 'not_owner'],
                resourceType: 'workbook',
            );

            abort(403, 'Only the workbook owner can delete it.');
        }

        $this->workbookService->delete($workbook);

        return response()->json(['message' => 'Workbook deleted.']);
    }

    public function history(Request $request, string $id): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $id, SharePermission::Read);
        $limit = (int) $request->query('limit', 50);

        return response()->json([
            'data' => $this->workbookService->history($workbook, $limit),
        ]);
    }
}
