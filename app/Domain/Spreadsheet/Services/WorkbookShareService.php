<?php

namespace App\Domain\Spreadsheet\Services;

use App\Domain\Spreadsheet\Enums\AuditAction;
use App\Domain\Spreadsheet\Enums\SharePermission;
use App\Domain\Spreadsheet\Events\WorkbookShared;
use App\Domain\Spreadsheet\Events\WorkbookShareRemoved;
use App\Domain\Spreadsheet\Events\WorkbookShareUpdated;
use App\Domain\Spreadsheet\Models\Workbook;
use App\Domain\Spreadsheet\Models\WorkbookShare;
use App\Models\User;

class WorkbookShareService
{
    public function __construct(
        private readonly WorkbookAccessService $accessService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function listShares(Workbook $workbook)
    {
        return WorkbookShare::query()
            ->where('workbook_id', $workbook->id)
            ->with(['user:id,name,email', 'sharedBy:id,name'])
            ->latest()
            ->get();
    }

    public function share(Workbook $workbook, User $owner, string $email, SharePermission $permission): WorkbookShare
    {
        $target = User::query()->where('email', $email)->firstOrFail();

        if ($target->id === $workbook->user_id) {
            throw new \InvalidArgumentException('Cannot share a workbook with its owner.');
        }

        $share = WorkbookShare::query()->updateOrCreate(
            ['workbook_id' => $workbook->id, 'user_id' => $target->id],
            ['shared_by' => $owner->id, 'permission' => $permission],
        )->load(['user:id,name,email', 'sharedBy:id,name']);

        $wasRecentlyCreated = $share->wasRecentlyCreated;

        event($wasRecentlyCreated
            ? new WorkbookShared($workbook, $share)
            : new WorkbookShareUpdated($workbook, $share));

        return $share->load(['user:id,name,email', 'sharedBy:id,name']);
    }

    public function updatePermission(Workbook $workbook, string $shareId, SharePermission $permission): WorkbookShare
    {
        $share = WorkbookShare::query()
            ->where('workbook_id', $workbook->id)
            ->where('id', $shareId)
            ->firstOrFail();

        $share->update(['permission' => $permission]);

        event(new WorkbookShareUpdated($workbook, $share->fresh()));

        return $share->fresh()->load(['user:id,name,email', 'sharedBy:id,name']);
    }

    public function remove(Workbook $workbook, string $shareId): void
    {
        $share = WorkbookShare::query()
            ->where('workbook_id', $workbook->id)
            ->where('id', $shareId)
            ->firstOrFail();

        $email = $share->user->email ?? 'unknown';
        $share->delete();

        event(new WorkbookShareRemoved($workbook, $email));
    }
}
