<?php

namespace App\Listeners;

use App\Domain\Spreadsheet\Enums\AuditAction;
use App\Domain\Spreadsheet\Events\CellsUpdated;
use App\Domain\Spreadsheet\Events\ChartChanged;
use App\Domain\Spreadsheet\Events\ColumnsDeleted;
use App\Domain\Spreadsheet\Events\ColumnsInserted;
use App\Domain\Spreadsheet\Events\ConditionalFormatChanged;
use App\Domain\Spreadsheet\Events\FilterApplied;
use App\Domain\Spreadsheet\Events\NamedRangeChanged;
use App\Domain\Spreadsheet\Events\OperationReverted;
use App\Domain\Spreadsheet\Events\RangeCleared;
use App\Domain\Spreadsheet\Events\RangeSorted;
use App\Domain\Spreadsheet\Events\RowsDeleted;
use App\Domain\Spreadsheet\Events\RowsInserted;
use App\Domain\Spreadsheet\Events\SheetCreated;
use App\Domain\Spreadsheet\Events\SheetDeleted;
use App\Domain\Spreadsheet\Events\SheetLayoutChanged;
use App\Domain\Spreadsheet\Events\SheetRenamed;
use App\Domain\Spreadsheet\Events\WorkbookCreated;
use App\Domain\Spreadsheet\Events\WorkbookDeleted;
use App\Domain\Spreadsheet\Events\WorkbookExported;
use App\Domain\Spreadsheet\Events\WorkbookImported;
use App\Domain\Spreadsheet\Events\WorkbookUpdated;
use App\Domain\Spreadsheet\Services\AuditLogService;

class RecordAuditLog
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function handleWorkbookCreated(WorkbookCreated $event): void
    {
        $this->auditLogService->record(
            AuditAction::WorkbookCreated,
            'Created workbook "'.$event->workbook->name.'"',
            $event->workbook,
        );
    }

    public function handleWorkbookUpdated(WorkbookUpdated $event): void
    {
        $parts = [];
        if (isset($event->changes['name'])) {
            $parts[] = 'renamed to "'.$event->changes['name']['to'].'"';
        }

        $this->auditLogService->record(
            AuditAction::WorkbookUpdated,
            'Updated workbook "'.$event->workbook->name.'"'.($parts ? ' ('.implode(', ', $parts).')' : ''),
            $event->workbook,
            details: $event->changes,
        );
    }

    public function handleWorkbookDeleted(WorkbookDeleted $event): void
    {
        $this->auditLogService->record(
            AuditAction::WorkbookDeleted,
            'Deleted workbook "'.$event->workbook->name.'"',
            $event->workbook,
        );
    }

    public function handleWorkbookImported(WorkbookImported $event): void
    {
        $this->auditLogService->record(
            AuditAction::WorkbookImported,
            'Imported workbook "'.$event->workbook->name.'"',
            $event->workbook,
        );
    }

    public function handleWorkbookExported(WorkbookExported $event): void
    {
        $this->auditLogService->record(
            AuditAction::WorkbookExported,
            'Exported workbook "'.$event->workbook->name.'"',
            $event->workbook,
        );
    }

    public function handleSheetCreated(SheetCreated $event): void
    {
        $this->auditLogService->record(
            AuditAction::SheetCreated,
            'Created sheet "'.$event->sheet->name.'" in "'.$event->workbook->name.'"',
            $event->workbook,
            $event->sheet,
        );
    }

    public function handleSheetRenamed(SheetRenamed $event): void
    {
        $this->auditLogService->record(
            AuditAction::SheetRenamed,
            'Renamed sheet "'.$event->oldName.'" to "'.$event->sheet->name.'"',
            $event->workbook,
            $event->sheet,
            target: $event->oldName.' → '.$event->sheet->name,
            details: ['from' => $event->oldName, 'to' => $event->sheet->name],
        );
    }

    public function handleSheetDeleted(SheetDeleted $event): void
    {
        $this->auditLogService->record(
            AuditAction::SheetDeleted,
            'Deleted sheet "'.$event->sheetName.'" from "'.$event->workbook->name.'"',
            $event->workbook,
            target: $event->sheetName,
            details: ['sheet_id' => $event->sheetId, 'sheet_name' => $event->sheetName],
        );
    }

    public function handleSheetLayoutChanged(SheetLayoutChanged $event): void
    {
        $parts = [];
        if (isset($event->changes['merged_cells'])) {
            $parts[] = 'merged cells';
        }
        if (isset($event->changes['hidden_rows'])) {
            $parts[] = 'hidden rows';
        }
        if (isset($event->changes['filters'])) {
            $parts[] = 'filters';
        }

        $label = $parts ? implode(', ', $parts) : 'layout';

        $this->auditLogService->record(
            AuditAction::SheetLayoutChanged,
            'Changed '.$label.' on "'.$event->sheet->name.'"',
            $event->workbook,
            $event->sheet,
            details: $event->changes,
        );
    }

    public function handleCellsUpdated(CellsUpdated $event): void
    {
        if ($event->auditSource === 'sort') {
            return;
        }

        $info = $this->auditLogService->summarizeCellChanges($event->changes);
        $count = $info['count'];
        $target = $this->formatCellTarget($info['cells']);

        $this->auditLogService->record(
            AuditAction::CellsUpdated,
            'Updated '.$count.' cell'.($count === 1 ? '' : 's').' on "'.$event->sheet->name.'"',
            $event->workbook,
            $event->sheet,
            target: $target,
            operationId: $event->operationId,
            details: $info,
        );
    }

    public function handleRangeCleared(RangeCleared $event): void
    {
        $info = $this->auditLogService->summarizeCellChanges($event->changes);

        $this->auditLogService->record(
            AuditAction::RangeCleared,
            'Cleared '.$info['count'].' cell'.($info['count'] === 1 ? '' : 's').' in '.$event->range,
            $event->workbook,
            $event->sheet,
            target: $event->range,
            operationId: $event->operationId,
            details: $info,
        );
    }

    public function handleRangeSorted(RangeSorted $event): void
    {
        $this->auditLogService->record(
            AuditAction::RangeSorted,
            'Sorted '.$event->range.' by column '.$event->column.' ('.$event->order.')',
            $event->workbook,
            $event->sheet,
            target: $event->range,
            operationId: $event->operationId,
            details: [
                'range' => $event->range,
                'column' => $event->column,
                'order' => $event->order,
            ],
        );
    }

    public function handleFilterApplied(FilterApplied $event): void
    {
        $this->auditLogService->record(
            AuditAction::FilterApplied,
            'Applied filter on "'.$event->sheet->name.'" ('.$event->hiddenRowCount.' rows hidden)',
            $event->workbook,
            $event->sheet,
            details: ['hidden_row_count' => $event->hiddenRowCount],
        );
    }

    public function handleRowsInserted(RowsInserted $event): void
    {
        $target = $event->count === 1
            ? 'row '.$event->atRow
            : $event->count.' rows at row '.$event->atRow;

        $this->auditLogService->record(
            AuditAction::RowsInserted,
            'Inserted '.$target.' on "'.$event->sheet->name.'"',
            $event->workbook,
            $event->sheet,
            target: (string) $event->atRow,
            details: ['at_row' => $event->atRow, 'count' => $event->count],
        );
    }

    public function handleRowsDeleted(RowsDeleted $event): void
    {
        $target = 'rows '.$event->startRow.'–'.$event->endRow;

        $this->auditLogService->record(
            AuditAction::RowsDeleted,
            'Deleted '.$target.' on "'.$event->sheet->name.'"',
            $event->workbook,
            $event->sheet,
            target: $target,
            details: [
                'start_row' => $event->startRow,
                'end_row' => $event->endRow,
            ],
        );
    }

    public function handleColumnsInserted(ColumnsInserted $event): void
    {
        $target = $event->count === 1
            ? 'column '.$event->atCol
            : $event->count.' columns at column '.$event->atCol;

        $this->auditLogService->record(
            AuditAction::ColumnsInserted,
            'Inserted '.$target.' on "'.$event->sheet->name.'"',
            $event->workbook,
            $event->sheet,
            target: (string) $event->atCol,
            details: ['at_col' => $event->atCol, 'count' => $event->count],
        );
    }

    public function handleColumnsDeleted(ColumnsDeleted $event): void
    {
        $target = 'columns '.$event->startCol.'–'.$event->endCol;

        $this->auditLogService->record(
            AuditAction::ColumnsDeleted,
            'Deleted '.$target.' on "'.$event->sheet->name.'"',
            $event->workbook,
            $event->sheet,
            target: $target,
            details: [
                'start_col' => $event->startCol,
                'end_col' => $event->endCol,
            ],
        );
    }

    public function handleOperationReverted(OperationReverted $event): void
    {
        $this->auditLogService->record(
            AuditAction::OperationReverted,
            'Reverted '.$event->revertedCount.' change'.($event->revertedCount === 1 ? '' : 's'),
            $event->workbook,
            operationId: $event->operationId,
            details: ['reverted_count' => $event->revertedCount],
        );
    }

    public function handleNamedRangeChanged(NamedRangeChanged $event): void
    {
        $this->auditLogService->record(
            AuditAction::NamedRangeChanged,
            ucfirst($event->action).' named range "'.$event->namedRange->name.'"',
            $event->workbook,
            target: $event->namedRange->name,
            details: [
                'action' => $event->action,
                'name' => $event->namedRange->name,
                'range' => $event->namedRange->range_a1,
            ],
        );
    }

    public function handleConditionalFormatChanged(ConditionalFormatChanged $event): void
    {
        $this->auditLogService->record(
            AuditAction::ConditionalFormatChanged,
            ucfirst($event->action).' conditional format on '.$event->conditionalFormat->range_a1,
            $event->workbook,
            target: $event->conditionalFormat->range_a1,
            details: ['action' => $event->action],
        );
    }

    public function handleChartChanged(ChartChanged $event): void
    {
        $this->auditLogService->record(
            AuditAction::ChartChanged,
            ucfirst($event->action).' chart "'.$event->chart->name.'"',
            $event->workbook,
            target: $event->chart->name,
            details: ['action' => $event->action],
        );
    }

    private function formatCellTarget(array $cells): ?string
    {
        if ($cells === []) {
            return null;
        }

        if (count($cells) <= 5) {
            return implode(', ', $cells);
        }

        return implode(', ', array_slice($cells, 0, 5)).' +'.(count($cells) - 5).' more';
    }
}
