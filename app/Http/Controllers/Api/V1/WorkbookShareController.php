<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Enums\SharePermission;
use App\Domain\Spreadsheet\Services\WorkbookAccessService;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Domain\Spreadsheet\Services\WorkbookShareService;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkbookShareResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkbookShareController extends Controller
{
    public function __construct(
        private readonly WorkbookService $workbookService,
        private readonly WorkbookShareService $shareService,
        private readonly WorkbookAccessService $accessService,
    ) {}

    public function index(Request $request, string $workbookId): AnonymousResourceCollection
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId, SharePermission::Read);

        if (! $this->accessService->isOwner($request->user(), $workbook)) {
            abort(403, 'Only the workbook owner can view shares.');
        }

        return WorkbookShareResource::collection($this->shareService->listShares($workbook));
    }

    public function store(Request $request, string $workbookId): WorkbookShareResource
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);

        if (! $this->accessService->isOwner($request->user(), $workbook)) {
            abort(403, 'Only the workbook owner can share workbooks.');
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'permission' => ['required', 'in:read,write'],
        ]);

        $share = $this->shareService->share(
            $workbook,
            $request->user(),
            $data['email'],
            SharePermission::from($data['permission']),
        );

        return new WorkbookShareResource($share);
    }

    public function update(Request $request, string $workbookId, string $shareId): WorkbookShareResource
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);

        if (! $this->accessService->isOwner($request->user(), $workbook)) {
            abort(403, 'Only the workbook owner can update shares.');
        }

        $data = $request->validate([
            'permission' => ['required', 'in:read,write'],
        ]);

        $share = $this->shareService->updatePermission(
            $workbook,
            $shareId,
            SharePermission::from($data['permission']),
        );

        return new WorkbookShareResource($share);
    }

    public function destroy(Request $request, string $workbookId, string $shareId): JsonResponse
    {
        $workbook = $this->workbookService->findForUser($request->user(), $workbookId);

        if (! $this->accessService->isOwner($request->user(), $workbook)) {
            abort(403, 'Only the workbook owner can remove shares.');
        }

        $this->shareService->remove($workbook, $shareId);

        return response()->json(['message' => 'Share removed.']);
    }
}
