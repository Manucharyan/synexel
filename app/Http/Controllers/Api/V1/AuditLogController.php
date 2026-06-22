<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Spreadsheet\Services\AuditLogService;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly WorkbookService $workbookService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'workbook_id' => ['nullable', 'uuid'],
            'action' => ['nullable', 'string', 'max:64'],
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        if (! empty($filters['workbook_id'])) {
            $this->workbookService->findForUser($request->user(), $filters['workbook_id']);
        }

        $logs = $this->auditLogService->listForUser($request->user(), $filters);

        return AuditLogResource::collection($logs);
    }
}
