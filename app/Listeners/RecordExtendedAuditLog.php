<?php

namespace App\Listeners;

use App\Domain\Spreadsheet\Enums\AuditAction;
use App\Domain\Spreadsheet\Events\CsvExported;
use App\Domain\Spreadsheet\Events\CsvImported;
use App\Domain\Spreadsheet\Events\GoogleSheetsImported;
use App\Domain\Spreadsheet\Events\WorkbookShared;
use App\Domain\Spreadsheet\Events\WorkbookShareRemoved;
use App\Domain\Spreadsheet\Events\WorkbookShareUpdated;
use App\Domain\Spreadsheet\Models\WebhookSubscription;
use App\Domain\Spreadsheet\Services\AuditLogService;

class RecordExtendedAuditLog
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function handleWorkbookShared(WorkbookShared $event): void
    {
        $this->auditLogService->record(
            AuditAction::ShareAdded,
            'Shared "'.$event->workbook->name.'" with '.$event->share->user->email.' ('.$event->share->permission->value.')',
            $event->workbook,
            target: $event->share->user->email,
            details: ['permission' => $event->share->permission->value],
            resourceType: 'workbook_share',
        );
    }

    public function handleWorkbookShareUpdated(WorkbookShareUpdated $event): void
    {
        $this->auditLogService->record(
            AuditAction::ShareUpdated,
            'Updated share for '.$event->share->user->email.' to '.$event->share->permission->value,
            $event->workbook,
            target: $event->share->user->email,
            details: ['permission' => $event->share->permission->value],
            resourceType: 'workbook_share',
        );
    }

    public function handleWorkbookShareRemoved(WorkbookShareRemoved $event): void
    {
        $this->auditLogService->record(
            AuditAction::ShareRemoved,
            'Removed share for '.$event->email.' from "'.$event->workbook->name.'"',
            $event->workbook,
            target: $event->email,
            resourceType: 'workbook_share',
        );
    }

    public function handleCsvImported(CsvImported $event): void
    {
        $this->auditLogService->record(
            AuditAction::CsvImported,
            'Imported CSV into "'.$event->workbook->name.'"',
            $event->workbook,
            resourceType: 'csv',
        );
    }

    public function handleCsvExported(CsvExported $event): void
    {
        $this->auditLogService->record(
            AuditAction::CsvExported,
            'Exported "'.$event->workbook->name.'" sheet "'.$event->sheetName.'" as CSV',
            $event->workbook,
            target: $event->sheetName,
            resourceType: 'csv',
        );
    }

    public function handleGoogleSheetsImported(GoogleSheetsImported $event): void
    {
        $this->auditLogService->record(
            AuditAction::GoogleSheetsImported,
            'Imported Google Sheet '.$event->spreadsheetId.' as "'.$event->workbook->name.'"',
            $event->workbook,
            target: $event->spreadsheetId,
            resourceType: 'google_sheets',
        );
    }
}
