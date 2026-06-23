<?php

use App\Domain\Spreadsheet\Enums\SharePermission;
use App\Domain\Spreadsheet\Services\WorkbookAccessService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('workbook.{workbookId}', function ($user, string $workbookId) {
    try {
        app(WorkbookAccessService::class)->findAccessible(
            $user,
            $workbookId,
            SharePermission::Read,
        );

        return ['id' => $user->id, 'name' => $user->name];
    } catch (\Throwable) {
        return false;
    }
});
